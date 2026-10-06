<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\OfferColorPreset;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * Instantanés nommés des couleurs et de l'ordre d'affichage des offres.
 *
 * @property \App\Model\Table\OfferColorPresetItemsTable&\Cake\ORM\Association\HasMany $OfferColorPresetItems
 *
 * @method \App\Model\Entity\OfferColorPreset newEmptyEntity()
 * @method \App\Model\Entity\OfferColorPreset newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\OfferColorPreset get($primaryKey, $options = [])
 * @method \App\Model\Entity\OfferColorPreset|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 */
class OfferColorPresetsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offer_color_presets');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('OfferColorPresetItems', [
            'foreignKey' => 'preset_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name', 'Le nom est obligatoire.');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name'], 'Ce nom de palette est déjà utilisé.'), [
            'errorField' => 'name',
        ]);

        return $rules;
    }

    /**
     * Fige les couleurs et l'ordre courants de toutes les offres.
     *
     * Seul le nom vient de l'appelant. Les hex et display_order sont lus en base.
     * L'ORM englobe le save associé dans une transaction.
     */
    public function capture(string $name): OfferColorPreset
    {
        $items = [];
        $offers = TableRegistry::getTableLocator()->get('Offers')->find()
            ->select(['id', 'color', 'display_order'])
            ->orderBy(['display_order' => 'ASC', 'id' => 'ASC'])
            ->all();

        foreach ($offers as $offer) {
            $items[] = [
                'offer_id' => (int)$offer->id,
                'color' => (string)$offer->color,
                'display_order' => (int)$offer->display_order,
            ];
        }

        $preset = $this->newEntity([
            'name' => trim($name),
            'offer_color_preset_items' => $items,
        ], [
            'associated' => ['OfferColorPresetItems'],
        ]);

        $this->save($preset, [
            'associated' => ['OfferColorPresetItems'],
        ]);

        return $preset;
    }

    /**
     * Écrit les offres de la palette, puis place les absentes après, sans changer leur couleur.
     *
     * L'ordre des présentes suit la famille, puis la position dans la famille,
     * puis l'ordre enregistré. Les anciennes palettes, sans famille, restent triées
     * par cet ordre enregistré.
     */
    public function restore(int $presetId): void
    {
        if (!$this->exists(['id' => $presetId])) {
            throw new RecordNotFoundException('Palette introuvable.');
        }

        $items = $this->OfferColorPresetItems->find()
            ->where(['preset_id' => $presetId])
            ->all()
            ->toList();

        $offers = TableRegistry::getTableLocator()->get('Offers');
        $currentOffers = $offers->find()
            ->select(['id', 'name', 'display_order'])
            ->all()
            ->toList();
        $byId = [];
        foreach ($currentOffers as $offer) {
            $byId[(int)$offer->id] = $offer;
        }

        $members = [];
        foreach ($items as $item) {
            if (isset($byId[(int)$item->offer_id])) {
                $members[] = $item;
            }
        }
        usort($members, function ($left, $right) use ($byId): int {
            return $this->comparePaletteMembers($left, $right, $byId);
        });

        $updates = [];
        $assigned = [];
        $order = 0;
        foreach ($members as $item) {
            $offerId = (int)$item->offer_id;
            $assigned[$offerId] = true;
            $updates[$offerId] = [
                'color' => (string)$item->color,
                'display_order' => $order,
            ];
            $order++;
        }

        $absent = [];
        foreach ($currentOffers as $offer) {
            if (!isset($assigned[(int)$offer->id])) {
                $absent[] = $offer;
            }
        }
        usort($absent, function ($left, $right): int {
            $displayOrder = ((int)$left->display_order) <=> ((int)$right->display_order);
            if ($displayOrder !== 0) {
                return $displayOrder;
            }
            $name = strcmp((string)$left->name, (string)$right->name);
            if ($name !== 0) {
                return $name;
            }

            return ((int)$left->id) <=> ((int)$right->id);
        });
        foreach ($absent as $offer) {
            $updates[(int)$offer->id] = ['display_order' => $order];
            $order++;
        }

        $write = function () use ($offers, $updates): void {
            foreach ($updates as $offerId => $fields) {
                $offers->updateAll($fields, ['id' => $offerId]);
            }
        };
        if ($this->getConnection()->inTransaction()) {
            $write();

            return;
        }
        $this->getConnection()->transactional($write);
    }

    /**
     * Familles à recopier dans le brouillon. Les offres sans famille n'y figurent pas.
     *
     * @return list<array{name:string,position:int,hue:int|string,pastel:bool,offer_ids:list<int>}>
     */
    public function arrangementPayload(int $presetId): array
    {
        $items = $this->OfferColorPresetItems->find()
            ->where(['preset_id' => $presetId])
            ->all();
        $groups = [];
        foreach ($items as $item) {
            $name = trim((string)$item->family_name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'name' => $name,
                    'position' => (int)$item->family_position,
                    'hue' => $item->hue === null ? '' : (int)$item->hue,
                    'pastel' => (bool)$item->pastel,
                    'offers' => [],
                ];
            }
            $groups[$key]['offers'][] = $item;
        }

        $groups = array_values($groups);
        usort($groups, function (array $left, array $right): int {
            return $left['position'] <=> $right['position'];
        });

        $payload = [];
        foreach ($groups as $group) {
            usort($group['offers'], function ($left, $right): int {
                return ((int)$left->position) <=> ((int)$right->position);
            });
            $offerIds = [];
            foreach ($group['offers'] as $item) {
                $offerIds[] = (int)$item->offer_id;
            }
            $payload[] = [
                'name' => $group['name'],
                'position' => $group['position'],
                'hue' => $group['hue'],
                'pastel' => $group['pastel'],
                'offer_ids' => $offerIds,
            ];
        }

        return $payload;
    }

    /**
     * Lignes copiables vers une nouvelle palette. Sans id.
     *
     * @return list<array<string, mixed>>
     */
    public function itemRows(int $presetId): array
    {
        $items = $this->OfferColorPresetItems->find()
            ->where(['preset_id' => $presetId])
            ->all();
        $rows = [];
        foreach ($items as $item) {
            $familyName = trim((string)$item->family_name);
            $rows[] = [
                'offer_id' => (int)$item->offer_id,
                'color' => (string)$item->color,
                'display_order' => (int)$item->display_order,
                'family_name' => $familyName === '' ? null : $familyName,
                'family_position' => $item->family_position === null ? null : (int)$item->family_position,
                'hue' => $item->hue === null ? null : (int)$item->hue,
                'pastel' => (bool)$item->pastel,
                'position' => $item->position === null ? null : (int)$item->position,
            ];
        }

        return $rows;
    }

    /**
     * Crée ou remplace une palette à partir des lignes déjà ordonnées.
     *
     * Retourne l'entité, « missing » si l'id ouvert n'existe plus, ou un message.
     *
     * @param list<array<string, mixed>> $items
     */
    public function storeSnapshot(?int $presetId, bool $asNew, string $name, array $items): OfferColorPreset|string
    {
        if (!$asNew && $presetId !== null && !$this->exists(['id' => $presetId])) {
            return 'missing';
        }

        if ($asNew || $presetId === null) {
            $preset = $this->newEntity([
                'name' => trim($name),
                'offer_color_preset_items' => $items,
            ], [
                'associated' => ['OfferColorPresetItems'],
            ]);
        } else {
            $preset = $this->get($presetId);
            $this->OfferColorPresetItems->deleteAll(['preset_id' => $presetId]);
            $preset = $this->patchEntity($preset, [
                'name' => trim($name),
                'offer_color_preset_items' => $items,
            ], [
                'associated' => ['OfferColorPresetItems'],
            ]);
        }

        $saved = $this->save($preset, [
            'associated' => ['OfferColorPresetItems'],
        ]);
        if ($saved === false) {
            $nameErrors = $preset->getError('name');

            return $nameErrors ? (string)reset($nameErrors) : 'La palette n\'a pas pu être enregistrée.';
        }

        return $saved;
    }

    /**
     * @param array<int, \App\Model\Entity\Offer> $offersById
     */
    private function comparePaletteMembers(object $left, object $right, array $offersById): int
    {
        $leftBare = trim((string)$left->family_name) === '';
        $rightBare = trim((string)$right->family_name) === '';
        if ($leftBare !== $rightBare) {
            return $leftBare <=> $rightBare;
        }
        if (!$leftBare) {
            $familyPosition = ((int)$left->family_position) <=> ((int)$right->family_position);
            if ($familyPosition !== 0) {
                return $familyPosition;
            }
            $position = ((int)$left->position) <=> ((int)$right->position);
            if ($position !== 0) {
                return $position;
            }
        }
        $displayOrder = ((int)$left->display_order) <=> ((int)$right->display_order);
        if ($displayOrder !== 0) {
            return $displayOrder;
        }
        $leftOffer = $offersById[(int)$left->offer_id] ?? null;
        $rightOffer = $offersById[(int)$right->offer_id] ?? null;
        $name = strcmp((string)($leftOffer->name ?? ''), (string)($rightOffer->name ?? ''));
        if ($name !== 0) {
            return $name;
        }

        return ((int)$left->offer_id) <=> ((int)$right->offer_id);
    }
}
