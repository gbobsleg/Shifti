<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class FixedActivityRulesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('fixed_activity_rules');
        $this->setPrimaryKey('id');
        $this->setDisplayField('id');

        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Sites', [
            'joinTable' => 'fixed_activity_rules_sites',
            'foreignKey' => 'rule_id',
            'targetForeignKey' => 'site_id',
        ]);
        $this->belongsToMany('IncompatibleOffers', [
            'className' => 'Offers',
            'joinTable' => 'fixed_activity_rules_incompatible_offers',
            'foreignKey' => 'fixed_activity_rule_id',
            'targetForeignKey' => 'offer_id',
        ]);
        $this->hasMany('FixedActivityBlocks', [
            'foreignKey' => 'fixed_activity_rule_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            // À chaque sauvegarde d'une règle, on remplace complètement la liste
            // des blocs existants par ceux envoyés depuis le formulaire.
            'saveStrategy' => 'replace',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('offer_id')->notEmptyString('offer_id')
            ->time('start_time')->notEmptyTime('start_time')
            ->time('end_time')->notEmptyTime('end_time')
            ->integer('quantity')->greaterThanOrEqual('quantity', 1)->notEmptyString('quantity')
            ->boolean('allow_shortfall')->allowEmptyString('allow_shortfall')
            ->boolean('active')->allowEmptyString('active')
            ->boolean('is_splittable')->allowEmptyString('is_splittable')
            ->boolean('equity_enabled')->allowEmptyString('equity_enabled')
            ->scalar('site_mode')->inList('site_mode', ['per_site', 'pooled', 'global'])->notEmptyString('site_mode')
            ->scalar('lunch_attach_mode')->inList('lunch_attach_mode', ['none', 'before', 'after'])->allowEmptyString('lunch_attach_mode')
            ->integer('sort_order')->allowEmptyString('sort_order')
            ->allowEmptyString('equity_group_id')
            ->maxLength('equity_group_id', 64);

        return $validator;
    }
}


