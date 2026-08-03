<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PlanningEventMappings Model
 *
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 *
 * @method \App\Model\Entity\PlanningEventMapping newEmptyEntity()
 * @method \App\Model\Entity\PlanningEventMapping newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\PlanningEventMapping[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\PlanningEventMapping get($primaryKey, $options = [])
 * @method \App\Model\Entity\PlanningEventMapping findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\PlanningEventMapping patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\PlanningEventMapping[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\PlanningEventMapping|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\PlanningEventMapping saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\PlanningEventMapping[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\PlanningEventMapping[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\PlanningEventMapping[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\PlanningEventMapping[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, array $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class PlanningEventMappingsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('planning_event_mappings');
        $this->setEntityClass(\App\Model\Entity\PlanningEventMapping::class);
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('keywords')
            ->maxLength('keywords', 255)
            ->allowEmptyString('keywords');

        $validator
            ->scalar('color_code')
            ->maxLength('color_code', 6)
            ->allowEmptyString('color_code')
            ->regex('color_code', '/^[0-9a-f]{6}$/i', 'Le code couleur doit être un hexadécimal de 6 caractères');

        $validator
            ->integer('offer_id')
            ->requirePresence('offer_id', 'create')
            ->notEmptyString('offer_id');

        $validator
            ->integer('priority')
            ->requirePresence('priority', 'create')
            ->notEmptyString('priority');

        // Validation custom : au moins keywords OU color_code doit être rempli
        $validator
            ->requirePresence(['keywords', 'color_code'], false)
            ->add('keywords', 'custom', [
                'rule' => function ($value, $context) {
                    $keywords = $context['data']['keywords'] ?? null;
                    $colorCode = $context['data']['color_code'] ?? null;
                    if (empty($keywords) && empty($colorCode)) {
                        return 'Au moins un des champs "keywords" ou "color_code" doit être rempli';
                    }
                    return true;
                },
            ]);

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), ['errorField' => 'offer_id']);

        return $rules;
    }

    /**
     * Trouve la règle correspondante pour un commentaire et/ou une couleur
     *
     * @param string $comment Commentaire Excel (normalisé en lowercase)
     * @param string|null $colorCode Code couleur normalisé (lowercase, sans #)
     * @return \App\Model\Entity\PlanningEventMapping|null
     */
    public function findMatchingRule(string $comment, ?string $colorCode = null): ?\App\Model\Entity\PlanningEventMapping
    {
        // Charger toutes les règles triées par priorité
        $mappings = $this->find()
            ->contain(['Offers'])
            ->order(['PlanningEventMappings.priority' => 'DESC', 'PlanningEventMappings.id' => 'ASC'])
            ->all();

        $commentNormalized = mb_strtolower($comment);
        $colorCodeNormalized = $colorCode ? strtolower($colorCode) : null;

        // SOLUTION 1 : Prioriser les keywords sur la couleur
        // Premier passage : chercher uniquement les règles qui matchent par keywords
        foreach ($mappings as $mapping) {
            // S'assurer que l'entité est du bon type
            if (!($mapping instanceof \App\Model\Entity\PlanningEventMapping)) {
                continue;
            }
            
            // Test uniquement les keywords (priorité absolue)
            if (!empty($mapping->keywords)) {
                $keywordNormalized = mb_strtolower($mapping->keywords);
                if (str_contains($commentNormalized, $keywordNormalized)) {
                    // Match par keywords trouvé : retourner immédiatement (même si une autre règle avec priorité plus élevée match par couleur)
                    return $mapping;
                }
            }
        }

        // Deuxième passage : si aucune règle n'a matché par keywords, chercher par couleur
        if ($colorCodeNormalized !== null) {
            foreach ($mappings as $mapping) {
                // S'assurer que l'entité est du bon type
                if (!($mapping instanceof \App\Model\Entity\PlanningEventMapping)) {
                    continue;
                }
                
                // Test uniquement la couleur (seulement si pas de keywords OU keywords n'a pas matché)
                if (!empty($mapping->color_code)) {
                    $mappingColorNormalized = strtolower($mapping->color_code);
                    if ($colorCodeNormalized === $mappingColorNormalized) {
                        // Match par couleur trouvé : retourner
                        return $mapping;
                    }
                }
            }
        }

        return null;
    }
}
