<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Lignes d'un instantané de couleurs d'offres.
 *
 * @property \App\Model\Table\OfferColorPresetsTable&\Cake\ORM\Association\BelongsTo $OfferColorPresets
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 *
 * @method \App\Model\Entity\OfferColorPresetItem newEmptyEntity()
 * @method \App\Model\Entity\OfferColorPresetItem newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\OfferColorPresetItem get($primaryKey, $options = [])
 * @method \App\Model\Entity\OfferColorPresetItem|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 */
class OfferColorPresetItemsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offer_color_preset_items');
        $this->setDisplayField('color');
        $this->setPrimaryKey('id');

        $this->belongsTo('OfferColorPresets', [
            'foreignKey' => 'preset_id',
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
            ->integer('preset_id')
            ->allowEmptyString('preset_id', null, 'create');

        $validator
            ->integer('offer_id')
            ->requirePresence('offer_id', 'create')
            ->notEmptyString('offer_id');

        $validator
            ->scalar('color')
            ->maxLength('color', 7)
            ->requirePresence('color', 'create')
            ->notEmptyString('color');

        $validator
            ->integer('display_order')
            ->requirePresence('display_order', 'create')
            ->notEmptyString('display_order');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['preset_id'], 'OfferColorPresets'), [
            'errorField' => 'preset_id',
            'message' => 'L\'instantané est invalide.',
        ]);
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), [
            'errorField' => 'offer_id',
            'message' => 'L\'offre est invalide.',
        ]);
        $rules->add($rules->isUnique(['preset_id', 'offer_id']), [
            'errorField' => 'offer_id',
            'message' => 'Cette offre est déjà dans l\'instantané.',
        ]);

        return $rules;
    }
}
