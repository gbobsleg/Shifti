<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\OfferGroup;
use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Association\HasMany;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * OfferGroups Model
 *
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $MixedOffers
 * @property \App\Model\Table\OfferGroupMembersTable&\Cake\ORM\Association\HasMany $OfferGroupMembers
 *
 * @method \App\Model\Entity\OfferGroup newEmptyEntity()
 * @method \App\Model\Entity\OfferGroup newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\OfferGroup[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\OfferGroup get(mixed $primaryKey, array|string $finder = 'all', ...$args)
 * @method \App\Model\Entity\OfferGroup findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\OfferGroup patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\OfferGroup[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\OfferGroup|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\OfferGroup saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class OfferGroupsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offer_groups');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('MixedOffers', [
            'className' => 'Offers',
            'foreignKey' => 'mixed_offer_id',
            'joinType' => 'INNER',
            'propertyName' => 'mixed_offer',
        ]);

        $this->hasMany('OfferGroupMembers', [
            'foreignKey' => 'offer_group_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'saveStrategy' => HasMany::SAVE_REPLACE,
            'sort' => ['OfferGroupMembers.display_order' => 'ASC', 'OfferGroupMembers.id' => 'ASC'],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->integer('mixed_offer_id')
            ->requirePresence('mixed_offer_id', 'create')
            ->notEmptyString('mixed_offer_id');

        $validator
            ->scalar('forecast_source')
            ->inList('forecast_source', [
                OfferGroup::FORECAST_SOURCE_MEMBERS,
                OfferGroup::FORECAST_SOURCE_GROUP,
            ], 'La source de forecast doit être "members" ou "group".')
            ->requirePresence('forecast_source', 'create')
            ->notEmptyString('forecast_source');

        $validator
            ->boolean('prefer_mixed')
            ->allowEmptyString('prefer_mixed');

        return $validator;
    }

    /**
     * En mode members, force split_ratio_percent à null sur les membres soumis.
     * Défaut prefer_mixed = true si absent.
     *
     * @param \Cake\Event\EventInterface<\App\Model\Table\OfferGroupsTable> $event Event
     * @param \ArrayObject<string, mixed> $data Données entrantes
     * @param \ArrayObject<string, mixed> $options Options de marshal
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        $copy = $data->getArrayCopy();

        if (!array_key_exists('prefer_mixed', $copy)) {
            $copy['prefer_mixed'] = true;
        }

        $source = $copy['forecast_source'] ?? null;
        if ($source === OfferGroup::FORECAST_SOURCE_MEMBERS
            && isset($copy['offer_group_members'])
            && is_array($copy['offer_group_members'])
        ) {
            foreach ($copy['offer_group_members'] as $index => $member) {
                if (is_array($member)) {
                    $copy['offer_group_members'][$index]['split_ratio_percent'] = null;
                }
            }
        }

        $data->exchangeArray($copy);
    }

    /**
     * Sécurité supplémentaire : nullifier les ratios juste avant persist en mode members.
     *
     * @param \Cake\Event\EventInterface<\App\Model\Table\OfferGroupsTable> $event Event
     * @param \Cake\Datasource\EntityInterface $entity Entité
     * @param \ArrayObject<string, mixed> $options Options
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$entity instanceof OfferGroup) {
            return;
        }

        if ($entity->forecast_source !== OfferGroup::FORECAST_SOURCE_MEMBERS) {
            return;
        }

        $members = $entity->offer_group_members;
        if (!is_iterable($members)) {
            return;
        }

        foreach ($members as $member) {
            $member->set('split_ratio_percent', null);
        }
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name'], 'Ce nom de groupe est déjà utilisé.'), [
            'errorField' => 'name',
        ]);
        $rules->add($rules->isUnique(['mixed_offer_id'], 'Cette offre mixte est déjà utilisée par un autre groupe.'), [
            'errorField' => 'mixed_offer_id',
        ]);
        $rules->add($rules->existsIn(['mixed_offer_id'], 'MixedOffers'), [
            'errorField' => 'mixed_offer_id',
            'message' => 'L\'offre mixte est invalide.',
        ]);

        $rules->add(
            [$this, 'ruleMixedOfferIsNormal'],
            'mixedOfferNormal',
            ['errorField' => 'mixed_offer_id', 'message' => 'L\'offre mixte doit être de type normal.']
        );
        $rules->add(
            [$this, 'ruleAtLeastTwoMembers'],
            'minMembers',
            [
                'errorField' => 'offer_group_members',
                'message' => 'Un groupe doit contenir au moins 2 offres membres.',
            ]
        );
        $rules->add(
            [$this, 'ruleMixedNotInMembers'],
            'mixedNotInMembers',
            [
                'errorField' => 'offer_group_members',
                'message' => 'L\'offre mixte ne peut pas être aussi un membre du groupe.',
            ]
        );
        $rules->add(
            [$this, 'ruleMemberOffersAreNormalAndExclusive'],
            'memberOffersExclusive',
            [
                'errorField' => 'offer_group_members',
                'message' => 'Chaque offre membre doit être de type normal et n\'appartenir qu\'à un seul groupe.',
            ]
        );
        $rules->add(
            [$this, 'ruleMixedOfferNotUsedAsMemberElsewhere'],
            'mixedNotMemberElsewhere',
            [
                'errorField' => 'mixed_offer_id',
                'message' => 'Cette offre est déjà membre d\'un autre groupe.',
            ]
        );
        $rules->add(
            [$this, 'ruleSplitRatiosSum100'],
            'ratiosSum100',
            [
                'errorField' => 'offer_group_members',
                'message' => 'Les ratios des membres doivent totaliser exactement 100 %.',
            ]
        );

        return $rules;
    }

    /**
     * Force une sauvegarde transactionnelle (groupe + membres associés).
     *
     * @param \Cake\Datasource\EntityInterface $entity Entité
     * @param array<string, mixed> $options Options
     * @return \Cake\Datasource\EntityInterface|false
     */
    public function save(EntityInterface $entity, array $options = []): EntityInterface|false
    {
        $options['atomic'] = $options['atomic'] ?? true;

        return parent::save($entity, $options);
    }

    public function ruleMixedOfferIsNormal(EntityInterface $entity, array $options): bool
    {
        $mixedOfferId = $entity->get('mixed_offer_id');
        if ($mixedOfferId === null || $mixedOfferId === '') {
            return false;
        }

        $offer = $this->MixedOffers->find()
            ->select(['id', 'offer_type'])
            ->where(['id' => $mixedOfferId])
            ->first();

        return $offer !== null && $offer->offer_type === 'normal';
    }

    public function ruleAtLeastTwoMembers(EntityInterface $entity, array $options): bool
    {
        $members = $this->resolveMembersForRules($entity);
        if ($members === null) {
            // Pas de membres dans ce save : laisser passer (update partiel sans associations).
            return true;
        }

        return count($members) >= 2;
    }

    public function ruleMixedNotInMembers(EntityInterface $entity, array $options): bool
    {
        $members = $this->resolveMembersForRules($entity);
        if ($members === null) {
            return true;
        }

        $mixedOfferId = (int)$entity->get('mixed_offer_id');
        foreach ($members as $member) {
            if ((int)$member->get('offer_id') === $mixedOfferId) {
                return false;
            }
        }

        return true;
    }

    public function ruleMemberOffersAreNormalAndExclusive(EntityInterface $entity, array $options): bool
    {
        $members = $this->resolveMembersForRules($entity);
        if ($members === null) {
            return true;
        }

        $offerIds = [];
        foreach ($members as $member) {
            $offerId = $member->get('offer_id');
            if ($offerId === null || $offerId === '') {
                $entity->setError('offer_group_members', [
                    '_required' => 'Chaque membre doit référencer une offre.',
                ]);

                return false;
            }
            $offerIds[] = (int)$offerId;
        }

        if ($offerIds === []) {
            return false;
        }

        if ($offerIds !== array_values(array_unique($offerIds))) {
            $entity->setError('offer_group_members', [
                'duplicate' => 'Une même offre ne peut apparaître qu\'une fois dans les membres.',
            ]);

            return false;
        }

        $offers = $this->MixedOffers->find()
            ->select(['id', 'offer_type'])
            ->where(['id IN' => $offerIds])
            ->all()
            ->indexBy('id')
            ->toArray();

        foreach ($offerIds as $offerId) {
            if (!isset($offers[$offerId]) || $offers[$offerId]->offer_type !== 'normal') {
                $entity->setError('offer_group_members', [
                    'offer_type' => 'Chaque offre membre doit exister et être de type normal.',
                ]);

                return false;
            }
        }

        $groupId = $entity->get('id');
        $conflictQuery = $this->OfferGroupMembers->find()
            ->where(['offer_id IN' => $offerIds]);
        if ($groupId) {
            $conflictQuery->andWhere(['offer_group_id !=' => $groupId]);
        }
        if ($conflictQuery->count() > 0) {
            $entity->setError('offer_group_members', [
                'exclusive' => 'Une offre membre appartient déjà à un autre groupe.',
            ]);

            return false;
        }

        $mixedConflict = $this->find()
            ->where(['mixed_offer_id IN' => $offerIds]);
        if ($groupId) {
            $mixedConflict->andWhere(['id !=' => $groupId]);
        }
        if ($mixedConflict->count() > 0) {
            $entity->setError('offer_group_members', [
                'exclusive_mixed' => 'Une offre membre est déjà utilisée comme offre mixte d\'un autre groupe.',
            ]);

            return false;
        }

        return true;
    }

    public function ruleMixedOfferNotUsedAsMemberElsewhere(EntityInterface $entity, array $options): bool
    {
        $mixedOfferId = $entity->get('mixed_offer_id');
        if ($mixedOfferId === null || $mixedOfferId === '') {
            return false;
        }

        $query = $this->OfferGroupMembers->find()
            ->where(['offer_id' => $mixedOfferId]);

        $groupId = $entity->get('id');
        if ($groupId) {
            $query->andWhere(['offer_group_id !=' => $groupId]);
        }

        return $query->count() === 0;
    }

    /**
     * En mode group : chaque ratio renseigné et Σ === 100.
     * En mode members : toujours OK (ratios nullifiés en beforeMarshal/beforeSave).
     */
    public function ruleSplitRatiosSum100(EntityInterface $entity, array $options): bool
    {
        if ($entity->get('forecast_source') !== OfferGroup::FORECAST_SOURCE_GROUP) {
            return true;
        }

        $members = $this->resolveMembersForRules($entity);
        if ($members === null) {
            return true;
        }

        $sum = 0;
        foreach ($members as $member) {
            $ratio = $member->get('split_ratio_percent');
            if ($ratio === null || $ratio === '') {
                $entity->setError('offer_group_members', [
                    'ratios_required' => sprintf(
                        'Les ratios des membres doivent totaliser exactement 100 %% (ratio manquant).'
                    ),
                ]);

                return false;
            }
            $sum += (int)$ratio;
        }

        if ($sum !== 100) {
            $entity->setError('offer_group_members', [
                'ratios_sum' => sprintf(
                    'Les ratios des membres doivent totaliser exactement 100 %% (actuellement %d %%).',
                    $sum
                ),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return list<\Cake\Datasource\EntityInterface>|null null = association absente de ce save
     */
    private function resolveMembersForRules(EntityInterface $entity): ?array
    {
        if (!$entity->has('offer_group_members')) {
            return null;
        }

        $members = $entity->get('offer_group_members');
        if ($members === null || !is_iterable($members)) {
            return [];
        }

        $list = [];
        foreach ($members as $member) {
            if ($member instanceof EntityInterface) {
                $list[] = $member;
            }
        }

        return $list;
    }
}
