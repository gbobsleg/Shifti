<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Service\OfferColors\FamilyShadeGenerator;
use Cake\Database\Exception\QueryException;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use PDOException;

/**
 * Familles visuelles d'offres.
 *
 * replaceArrangement() n'écrit ni la couleur ni l'ordre d'affichage.
 * publishArrangement() réécrit les deux à partir du rangement.
 *
 * @property \App\Model\Table\OfferColorFamilyOffersTable&\Cake\ORM\Association\HasMany $OfferColorFamilyOffers
 * @method \App\Model\Entity\OfferColorFamily newEmptyEntity()
 * @method \App\Model\Entity\OfferColorFamily newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\OfferColorFamily get($primaryKey, $options = [])
 * @method \App\Model\Entity\OfferColorFamily|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 */
class OfferColorFamiliesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offer_color_families');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('OfferColorFamilyOffers', [
            'foreignKey' => 'family_id',
            'sort' => ['OfferColorFamilyOffers.position' => 'ASC', 'OfferColorFamilyOffers.id' => 'ASC'],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255, 'Le nom de la famille est trop long.')
            ->requirePresence('name', 'create')
            ->notEmptyString('name', 'Le nom de la famille est obligatoire.');

        $validator
            ->integer('position')
            ->requirePresence('position', 'create')
            ->notEmptyString('position');

        $validator
            ->integer('hue')
            ->allowEmptyString('hue')
            ->inList(
                'hue',
                array_keys(FamilyShadeGenerator::CATALOG),
                'La teinte d\'une famille est invalide.',
            );

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name'], 'Ce nom de famille est déjà utilisé.'), [
            'errorField' => 'name',
        ]);

        return $rules;
    }

    /**
     * Remplace tout le rangement par le payload.
     *
     * Retourne un message d'erreur, ou null si l'enregistrement a réussi.
     * Les contrôles métier ont lieu avant la transaction. Une PDOException
     * résiduelle (offre supprimée entre-temps) est convertie en message.
     *
     * @param array<mixed> $families Payload `families`
     * @return string|null
     */
    public function replaceArrangement(array $families): ?string
    {
        $parsed = $this->parseArrangement($families);
        if (is_string($parsed)) {
            return $parsed;
        }

        $errorMessage = null;
        try {
            $committed = $this->getConnection()->transactional(function () use ($parsed, &$errorMessage) {
                $errorMessage = $this->insertArrangement($parsed);

                return $errorMessage === null;
            });
        } catch (QueryException | PDOException) {
            return 'Le rangement n\'a pas pu être enregistré.';
        }

        if ($committed === false) {
            return $errorMessage ?? 'Le rangement n\'a pas pu être enregistré.';
        }

        return null;
    }

    /**
     * Enregistre le rangement et publie couleurs et ordre d'affichage.
     *
     * Les couleurs écrites sont celles du payload, pas une génération serveur.
     * Une teinte « Automatique » est choisie en mémoire avant l'insertion.
     * Retourne un message d'erreur, ou null si l'application a réussi.
     *
     * @param array<mixed> $families Payload `families`
     * @return string|null
     */
    public function publishArrangement(array $families): ?string
    {
        $parsed = $this->parseArrangement($families);
        if (is_string($parsed)) {
            return $parsed;
        }

        $colors = $this->parsePublishedColors($families, $parsed);
        if (is_string($colors)) {
            return $colors;
        }

        $parsed = $this->resolveAutomaticHues($parsed);
        $offers = TableRegistry::getTableLocator()->get('Offers');
        $currentOffers = $offers->find()
            ->select(['id', 'name', 'display_order'])
            ->all()
            ->toList();

        $orderedIds = [];
        $assigned = [];
        $byPosition = $parsed;
        usort($byPosition, function (array $left, array $right): int {
            return $left['position'] <=> $right['position'];
        });
        foreach ($byPosition as $family) {
            foreach ($family['offer_ids'] as $offerId) {
                $assigned[$offerId] = true;
                $orderedIds[] = $offerId;
            }
        }

        $unassigned = [];
        foreach ($currentOffers as $offer) {
            $offerId = (int)$offer->id;
            if (!isset($assigned[$offerId])) {
                $unassigned[] = $offer;
            }
        }
        usort($unassigned, function ($left, $right): int {
            $order = ((int)$left->display_order) <=> ((int)$right->display_order);
            if ($order !== 0) {
                return $order;
            }
            $name = strcmp((string)$left->name, (string)$right->name);
            if ($name !== 0) {
                return $name;
            }

            return ((int)$left->id) <=> ((int)$right->id);
        });
        foreach ($unassigned as $offer) {
            $orderedIds[] = (int)$offer->id;
        }

        $errorMessage = null;
        try {
            $committed = $this->getConnection()->transactional(
                function () use ($parsed, $offers, $orderedIds, $colors, &$errorMessage) {
                    $errorMessage = $this->insertArrangement($parsed);
                    if ($errorMessage !== null) {
                        return false;
                    }
                    foreach ($orderedIds as $displayOrder => $offerId) {
                        $fields = ['display_order' => $displayOrder];
                        if (isset($colors[$offerId])) {
                            $fields['color'] = $colors[$offerId];
                        }
                        $offers->updateAll($fields, ['id' => $offerId]);
                    }

                    return true;
                },
            );
        } catch (QueryException | PDOException) {
            return 'L\'application au planning n\'a pas pu être enregistrée.';
        }

        if ($committed === false) {
            return $errorMessage ?? 'L\'application au planning n\'a pas pu être enregistrée.';
        }

        return null;
    }

    /**
     * @param array<mixed> $families
     * @return list<array{name:string,position:int,hue:int|null,offer_ids:list<int>}>|string
     */
    private function parseArrangement(array $families): array|string
    {
        $parsed = [];
        $seenOfferIds = [];
        $seenNames = [];

        foreach (array_values($families) as $family) {
            if (!is_array($family)) {
                return 'Le rangement est invalide.';
            }

            $name = trim((string)($family['name'] ?? ''));
            if ($name === '') {
                return 'Le nom de la famille est obligatoire.';
            }
            if (mb_strlen($name) > 255) {
                return 'Le nom de la famille est trop long.';
            }
            $nameKey = mb_strtolower($name);
            if (isset($seenNames[$nameKey])) {
                return 'Ce nom de famille est déjà utilisé.';
            }
            $seenNames[$nameKey] = true;

            $position = $this->toInt($family['position'] ?? null);
            if ($position === null) {
                return 'La position d\'une famille est invalide.';
            }

            $offerIds = [];
            if (array_key_exists('offer_ids', $family) && $family['offer_ids'] !== null && $family['offer_ids'] !== '') {
                if (!is_array($family['offer_ids'])) {
                    return 'Une offre du rangement est invalide.';
                }
                foreach (array_values($family['offer_ids']) as $rawId) {
                    $offerId = $this->toInt($rawId);
                    if ($offerId === null || $offerId <= 0) {
                        return 'Une offre du rangement est invalide.';
                    }
                    if (isset($seenOfferIds[$offerId])) {
                        return 'Une offre est rangée dans plusieurs familles.';
                    }
                    $seenOfferIds[$offerId] = true;
                    $offerIds[] = $offerId;
                }
            }

            $hue = null;
            if (array_key_exists('hue', $family) && $family['hue'] !== null && $family['hue'] !== '') {
                $hue = $this->toInt($family['hue']);
                if ($hue === null || !array_key_exists($hue, FamilyShadeGenerator::CATALOG)) {
                    return 'La teinte d\'une famille est invalide.';
                }
            }

            $parsed[] = [
                'name' => $name,
                'position' => $position,
                'hue' => $hue,
                'offer_ids' => $offerIds,
            ];
        }

        if ($seenOfferIds !== []) {
            $offers = TableRegistry::getTableLocator()->get('Offers');
            $found = $offers->find()
                ->select(['id'])
                ->where(['id IN' => array_keys($seenOfferIds)])
                ->all()
                ->extract('id')
                ->toList();
            $found = array_map('intval', $found);
            sort($found);
            $expected = array_keys($seenOfferIds);
            sort($expected);
            if ($found !== $expected) {
                return 'Une offre du rangement n\'existe plus.';
            }
        }

        return $parsed;
    }

    /**
     * @param list<array{name:string,position:int,hue:int|null,offer_ids:list<int>}> $parsed
     */
    private function insertArrangement(array $parsed): ?string
    {
        $this->deleteAll(['id IS NOT' => null]);

        foreach ($parsed as $family) {
            $members = [];
            foreach ($family['offer_ids'] as $position => $offerId) {
                $members[] = [
                    'offer_id' => $offerId,
                    'position' => $position,
                ];
            }
            $entity = $this->newEntity([
                'name' => $family['name'],
                'position' => $family['position'],
                'hue' => $family['hue'],
                'offer_color_family_offers' => $members,
            ], [
                'associated' => ['OfferColorFamilyOffers'],
            ]);
            $saved = $this->save($entity, [
                'associated' => ['OfferColorFamilyOffers'],
            ]);
            if ($saved === false) {
                $nameErrors = $entity->getError('name');

                return $nameErrors ? (string)reset($nameErrors) : 'Le rangement n\'a pas pu être enregistré.';
            }
        }

        return null;
    }

    /**
     * Couleurs affichées, dans le même ordre que les offres de chaque famille.
     *
     * @param array<mixed> $families
     * @param list<array{name:string,position:int,hue:int|null,offer_ids:list<int>}> $parsed
     * @return array<int, string>|string
     */
    private function parsePublishedColors(array $families, array $parsed): array|string
    {
        $families = array_values($families);
        if (count($families) !== count($parsed)) {
            return 'Les couleurs affichées sont incomplètes.';
        }

        $colors = [];
        foreach ($parsed as $index => $family) {
            if ($family['offer_ids'] === []) {
                continue;
            }
            $raw = $families[$index]['colors'] ?? null;
            if (!is_array($raw) || count($raw) !== count($family['offer_ids'])) {
                return 'Les couleurs affichées sont incomplètes.';
            }
            foreach ($family['offer_ids'] as $offerIndex => $offerId) {
                $hex = strtolower(trim((string)$raw[$offerIndex]));
                if (preg_match('/^#[0-9a-f]{6}$/', $hex) !== 1) {
                    return 'Une couleur affichée est invalide.';
                }
                $colors[$offerId] = $hex;
            }
        }

        return $colors;
    }

    /**
     * Remplace les teintes nulles par une teinte du catalogue, en mémoire.
     *
     * @param list<array{name:string,position:int,hue:int|null,offer_ids:list<int>}> $parsed
     * @return list<array{name:string,position:int,hue:int,offer_ids:list<int>}>
     */
    private function resolveAutomaticHues(array $parsed): array
    {
        $catalog = array_keys(FamilyShadeGenerator::CATALOG);
        $counts = array_fill_keys($catalog, 0);
        foreach ($parsed as $family) {
            if ($family['hue'] !== null) {
                $counts[$family['hue']]++;
            }
        }

        foreach ($parsed as $index => $family) {
            if ($family['hue'] !== null) {
                continue;
            }
            $chosen = null;
            foreach ($catalog as $hue) {
                if ($counts[$hue] === 0) {
                    $chosen = $hue;
                    break;
                }
            }
            if ($chosen === null) {
                $least = min($counts);
                foreach ($catalog as $hue) {
                    if ($counts[$hue] === $least) {
                        $chosen = $hue;
                        break;
                    }
                }
            }
            $parsed[$index]['hue'] = $chosen;
            $counts[$chosen]++;
        }

        return $parsed;
    }

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int)$value;
        }

        return null;
    }
}
