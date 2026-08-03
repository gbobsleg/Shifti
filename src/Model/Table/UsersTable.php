<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;

/**
 * Users Model
 *
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsTo $Roles
 * @property \App\Model\Table\SitesTable&\Cake\ORM\Association\BelongsTo $Sites
 * @property \App\Model\Table\RangesTable&\Cake\ORM\Association\HasMany $Ranges
 *
 * @method \App\Model\Entity\User newEmptyEntity()
 * @method \App\Model\Entity\User newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\User[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\User get($primaryKey, $options = [])
 * @method \App\Model\Entity\User findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\User[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\User|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UsersTable extends Table
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

        $this->setTable('users');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Roles', [
            'foreignKey' => 'role_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Sites', [
            'foreignKey' => 'site_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('Ranges', [
            'foreignKey' => 'user_id',
        ]);

        // Brouillons de planning (planning_range_drafts)
        // Propriété côté entity: $user->draft_ranges
        $this->hasMany('DraftRanges', [
            'className' => 'DraftRanges',
            'foreignKey' => 'user_id',
        ]);
		// Relation pour la table de jointure SKILLS
		$this->hasMany('Skills', [
			'foreignKey' => 'user_id',
		]);
		// Relation "Many-to-Many" (Un utilisateur a plusieurs offres via la table skills)
		$this->belongsToMany('Offers', [
			'foreignKey' => 'user_id',
			'targetForeignKey' => 'offer_id',
			'joinTable' => 'skills', // Nom de la table de jointure
		]);
        $this->hasMany('UserAvailabilities', [
            'foreignKey' => 'user_id',
            'sort' => ['day_of_week' => 'ASC'] // Pour les avoir de Lundi à Dimanche
        ]);
        $this->hasOne('UserRemoteWorkSetting', [
            'className' => 'UserRemoteWorkSettings',
            'foreignKey' => 'user_id',
        ]);
        $this->hasOne('UsersRotationRule', [
            'className' => 'UsersRotationRules',
            'foreignKey' => 'user_id',
        ]);
        $this->hasMany('UserContracts', [
            'foreignKey' => 'user_id',
            'dependent' => true,
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
            ->scalar('user_code')
            ->maxLength('user_code', 11)
            ->requirePresence('user_code', 'create')
            ->notEmptyString('user_code');

        $validator
            ->scalar('last_name')
            ->maxLength('last_name', 255)
            ->requirePresence('last_name', 'create')
            ->notEmptyString('last_name');

        $validator
            ->scalar('first_name')
            ->maxLength('first_name', 255)
            ->requirePresence('first_name', 'create')
            ->notEmptyString('first_name');

        $validator
            ->email('email')
            ->requirePresence('email', 'create')
            ->notEmptyString('email');

        $validator
            ->scalar('password')
            ->maxLength('password', 255)
            ->requirePresence('password', 'create')
            ->notEmptyString('password');

        return $validator;
    }
    
    /**
     * Hashage du mot de passe avant sauvegarde
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, \ArrayObject $options)
    {
        if ($entity->isDirty('password') && !empty($entity->password)) {
            $hasher = new DefaultPasswordHasher();
            $entity->password = $hasher->hash($entity->password);
        }
        return true;
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
        $rules->add($rules->isUnique(['email']), ['errorField' => 'email']);
        $rules->add($rules->existsIn(['role_id'], 'Roles'), ['errorField' => 'role_id']);
        $rules->add($rules->existsIn(['site_id'], 'Sites'), ['errorField' => 'site_id']);

        return $rules;
    }

    public function findThisDay(Query $query, array $options)
    {
        $params = $options['params'];

        $day_ranges = $options['day_ranges'];

        // Appliquer le filtre contractuel d'abord
        $query = $this->findActiveInPeriod($query, [
            'period' => [
                'begin' => $day_ranges['begin'],
                'end' => $day_ranges['end'],
            ]
        ]);

//        debug($day_ranges); exit;

        $query->contain(['Roles', 'Sites'])
            ->contain('Ranges', function ($q) use ($day_ranges) {
                return $q->where([
                    'AND' => [
                        ['Ranges.date_start <=' => $day_ranges['end']],
                        ['Ranges.date_end >=' => $day_ranges['begin']]
                    ]
                ]);
            })
            ->contain('Ranges.Offers');

        if (!empty($params['user_id'])) {
            $query->where(['Users.id = ' . $params['user_id']]);
        }
        $offerIds = [];
        if (isset($params['offer_id'])) {
            $raw = $params['offer_id'];
            $offerIds = is_array($raw)
                ? array_values(array_filter(array_map('intval', $raw)))
                : ((int)$raw > 0 ? [(int)$raw] : []);
        }
        if (!empty($offerIds)) {
            $query->innerJoinWith('Ranges', function ($q) use ($offerIds, $day_ranges) {
                return $q->where([
                    'Ranges.offer_id IN' => $offerIds,
                    'Ranges.date_start <=' => $day_ranges['end'],
                    'Ranges.date_end >=' => $day_ranges['begin']
                ]);
            });
        }
        if (!empty($params['site_id'])) {
            // Filtrer sur la table principale Users pour éviter d'injecter une condition Users.* dans une requête Sites
            $query->where(['Users.site_id' => $params['site_id']]);
        }
        return $query;
    }

    /**
     * Finder pour recuperer les utilisateurs ayant un contrat actif 
     * qui chevauche la periode specifiee.
     * 
     * Logique d'intersection : Un contrat [start_date, end_date] chevauche 
     * une periode [begin, end] si :
     *   start_date <= end AND (end_date IS NULL OR end_date >= begin)
     *
     * @param \Cake\ORM\Query $query
     * @param array $options Doit contenir 'period' => ['begin' => Date, 'end' => Date]
     * @return \Cake\ORM\Query
     */
    public function findActiveInPeriod(Query $query, array $options): Query
    {
        $period = $options['period'] ?? null;
        
        if (!$period || empty($period['begin']) || empty($period['end'])) {
            // Sans periode, retourne tous les utilisateurs (fallback)
            return $query;
        }
        
        $periodBegin = $period['begin'];
        $periodEnd = $period['end'];
        
        // Formater en date si ce sont des objets DateTime
        if ($periodBegin instanceof \DateTimeInterface) {
            $periodBegin = $periodBegin->format('Y-m-d');
        }
        if ($periodEnd instanceof \DateTimeInterface) {
            $periodEnd = $periodEnd->format('Y-m-d');
        }
        
        return $query
            ->innerJoinWith('UserContracts', function ($q) use ($periodBegin, $periodEnd) {
                return $q->where([
                    'UserContracts.start_date <=' => $periodEnd,
                    'OR' => [
                        'UserContracts.end_date IS' => null,
                        'UserContracts.end_date >=' => $periodBegin,
                    ],
                ]);
            })
            ->distinct('Users.id');
    }

	/**
	 * Finder pour obtenir les utilisateurs sous le format Nom Prénom, triés par Nom.
	 */
	public function findListFullname(Query $query, array $options): Query
	{
		return $query
			->select([
				'id',
				// Concaténation des deux champs (Nom, Prénom) pour l'affichage
				'full_name' => $query->func()->concat([
					$query->identifier('Users.last_name'),
					' ',
					$query->identifier('Users.first_name')
				]),
			])
			// AJOUT DE LA CLAUSE DE TRI PAR NOM DE FAMILLE (last_name)
			->order([
				'Users.last_name' => 'ASC',
				'Users.first_name' => 'ASC', // Tri secondaire par prénom
			])
			->find('list', [
				'keyField' => 'id',
				'valueField' => 'full_name',
			]);
	}
}
