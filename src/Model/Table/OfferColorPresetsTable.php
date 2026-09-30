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
     * Réécrit color et display_order des offres encore présentes dans l'instantané.
     *
     * Les offres absentes de l'instantané ne sont pas modifiées.
     * Une offre supprimée n'a plus de ligne (ON DELETE CASCADE).
     */
    public function restore(int $presetId): void
    {
        if (!$this->exists(['id' => $presetId])) {
            throw new RecordNotFoundException('Palette introuvable.');
        }

        $items = $this->OfferColorPresetItems->find()
            ->select(['id', 'offer_id', 'color', 'display_order'])
            ->where(['preset_id' => $presetId])
            ->all();

        $offers = TableRegistry::getTableLocator()->get('Offers');
        $this->getConnection()->transactional(function () use ($items, $offers): void {
            foreach ($items as $item) {
                $offers->updateAll(
                    [
                        'color' => (string)$item->color,
                        'display_order' => (int)$item->display_order,
                    ],
                    ['id' => (int)$item->offer_id]
                );
            }
        });
    }
}
