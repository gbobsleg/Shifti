<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Offers Model
 *
 * @property \App\Model\Table\RangesTable&\Cake\ORM\Association\HasMany $Ranges
 *
 * @method \App\Model\Entity\Offer newEmptyEntity()
 * @method \App\Model\Entity\Offer newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Offer[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Offer get($primaryKey, $options = [])
 * @method \App\Model\Entity\Offer findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Offer patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Offer[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Offer|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Offer saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Offer[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Offer[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Offer[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Offer[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class OffersTable extends Table
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

        $this->setTable('offers');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Ranges', [
            'foreignKey' => 'offer_id',
        ]);
		
		// Relation pour la table de jointure SKILLS
		$this->hasMany('Skills', [
			'foreignKey' => 'offer_id',
		]);
		
		// Relation "Many-to-Many" (Une offre a plusieurs utilisateurs via la table skills)
		$this->belongsToMany('Users', [
			'foreignKey' => 'offer_id',
			'targetForeignKey' => 'user_id',
			'joinTable' => 'skills', // Nom de la table de jointure
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->date('start_date')
            ->allowEmptyDate('start_date');

        $validator
            ->date('end_date')
            ->allowEmptyDate('end_date');

        $validator
            ->scalar('color')
            ->maxLength('color', 7)
            ->requirePresence('color', 'create')
            ->notEmptyString('color');

        $validator
            ->scalar('offer_type')
            ->inList('offer_type', ['normal', 'absence', 'remote_work', 'pause', 'lunch'])
            ->requirePresence('offer_type', 'create')
            ->notEmptyString('offer_type');

        $validator
            ->integer('display_order')
            ->requirePresence('display_order', 'create')
            ->notEmptyString('display_order');

        $validator
            ->boolean('is_displayed_in_grid')
            ->requirePresence('is_displayed_in_grid', 'create')
            ->notEmptyString('is_displayed_in_grid');

        $validator
            ->boolean('is_forecastable')
            ->requirePresence('is_forecastable', 'create')
            ->notEmptyString('is_forecastable');

        $validator
            ->scalar('default_forecast_method')
            ->inList('default_forecast_method', ['historical', 'prophet'])
            ->notEmptyString('default_forecast_method');

        $validator
            ->boolean('equity_enabled')
            ->requirePresence('equity_enabled', 'create')
            ->notEmptyString('equity_enabled');

        $validator
            ->boolean('is_remote_work_compatible')
            ->requirePresence('is_remote_work_compatible', 'create')
            ->notEmptyString('is_remote_work_compatible');

        return $validator;
    }

    /**
     * Injecte la méthode de prévision par défaut si absente du payload.
     *
     * Ne touche pas la clé si elle est présente (même nulle ou invalide) :
     * la validation inList s'en charge.
     *
     * @param \Cake\Event\EventInterface $event Event
     * @param \ArrayObject $data Données entrantes
     * @param \ArrayObject $options Options de marshal
     * @return void
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        // ArrayObject : array_key_exists() sur la copie tableau (évite TypeError PHP 8+)
        if (!array_key_exists('default_forecast_method', $data->getArrayCopy())) {
            $data['default_forecast_method'] = 'historical';
        }
    }

    /**
     * Finder pour les offres affichées dans la grille
     */
    public function findDisplayedInGrid(Query $query, array $options): Query
    {
        return $query->where(['is_displayed_in_grid' => true])
            ->order(['display_order' => 'ASC', 'name' => 'ASC']);
    }

    /**
     * Finder pour les offres forecastables
     */
    public function findForecastable(Query $query, array $options): Query
    {
        return $query->where(['is_forecastable' => true]);
    }

    /**
     * Finder par type d'offre
     */
    public function findByType(Query $query, array $options): Query
    {
        if (!isset($options['type'])) {
            return $query;
        }

        return $query->where(['offer_type' => $options['type']]);
    }
}
