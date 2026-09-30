<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Offre rangée dans une famille visuelle.
 *
 * @property \App\Model\Table\OfferColorFamiliesTable&\Cake\ORM\Association\BelongsTo $OfferColorFamilies
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 *
 * @method \App\Model\Entity\OfferColorFamilyOffer newEmptyEntity()
 * @method \App\Model\Entity\OfferColorFamilyOffer newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\OfferColorFamilyOffer get($primaryKey, $options = [])
 * @method \App\Model\Entity\OfferColorFamilyOffer|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 */
class OfferColorFamilyOffersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offer_color_family_offers');
        $this->setDisplayField('offer_id');
        $this->setPrimaryKey('id');

        $this->belongsTo('OfferColorFamilies', [
            'foreignKey' => 'family_id',
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
            ->integer('family_id')
            ->allowEmptyString('family_id', null, 'create');

        $validator
            ->integer('offer_id')
            ->requirePresence('offer_id', 'create')
            ->notEmptyString('offer_id');

        $validator
            ->integer('position')
            ->requirePresence('position', 'create')
            ->notEmptyString('position');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['family_id'], 'OfferColorFamilies'), [
            'errorField' => 'family_id',
            'message' => 'La famille est invalide.',
        ]);
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), [
            'errorField' => 'offer_id',
            'message' => 'L\'offre est invalide.',
        ]);
        $rules->add($rules->isUnique(['offer_id']), [
            'errorField' => 'offer_id',
            'message' => 'Cette offre est déjà rangée dans une famille.',
        ]);

        return $rules;
    }
}
