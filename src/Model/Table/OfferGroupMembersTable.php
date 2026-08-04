<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * OfferGroupMembers Model
 *
 * @property \App\Model\Table\OfferGroupsTable&\Cake\ORM\Association\BelongsTo $OfferGroups
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 *
 * @method \App\Model\Entity\OfferGroupMember newEmptyEntity()
 * @method \App\Model\Entity\OfferGroupMember newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\OfferGroupMember[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\OfferGroupMember get(mixed $primaryKey, array|string $finder = 'all', ...$args)
 * @method \App\Model\Entity\OfferGroupMember findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\OfferGroupMember patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\OfferGroupMember[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\OfferGroupMember|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\OfferGroupMember saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class OfferGroupMembersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offer_group_members');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('OfferGroups', [
            'foreignKey' => 'offer_group_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('offer_group_id')
            ->allowEmptyString('offer_group_id');

        $validator
            ->integer('offer_id')
            ->requirePresence('offer_id', 'create')
            ->notEmptyString('offer_id');

        $validator
            ->integer('display_order')
            ->requirePresence('display_order', 'create');

        $validator
            ->integer('split_ratio_percent')
            ->allowEmptyString('split_ratio_percent')
            ->range('split_ratio_percent', [0, 100], 'Le ratio doit être compris entre 0 et 100.');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['offer_group_id'], 'OfferGroups'), [
            'errorField' => 'offer_group_id',
            'message' => 'Le groupe d\'offres est invalide.',
        ]);
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), [
            'errorField' => 'offer_id',
            'message' => 'L\'offre membre est invalide.',
        ]);
        $rules->add($rules->isUnique(['offer_group_id', 'offer_id']), [
            'errorField' => 'offer_id',
            'message' => 'Cette offre est déjà membre de ce groupe.',
        ]);
        $rules->add($rules->isUnique(['offer_id']), [
            'errorField' => 'offer_id',
            'message' => 'Cette offre appartient déjà à un autre groupe.',
        ]);

        return $rules;
    }
}
