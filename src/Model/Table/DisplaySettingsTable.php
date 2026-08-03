<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * DisplaySettings Model
 *
 * @method \App\Model\Entity\DisplaySetting newEmptyEntity()
 * @method \App\Model\Entity\DisplaySetting newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\DisplaySetting> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\DisplaySetting get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\DisplaySetting findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\DisplaySetting patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\DisplaySetting> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\DisplaySetting|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\DisplaySetting saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\DisplaySetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\DisplaySetting>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\DisplaySetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\DisplaySetting> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\DisplaySetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\DisplaySetting>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\DisplaySetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\DisplaySetting> deleteManyOrFail(iterable $entities, array $options = [])
 */
class DisplaySettingsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('display_settings');
        $this->setDisplayField('key');
        $this->setPrimaryKey('id');
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
            ->scalar('key')
            ->maxLength('key', 255)
            ->requirePresence('key', 'create')
            ->notEmptyString('key')
            ->add('key', 'unique', [
                'rule' => 'validateUnique',
                'provider' => 'table',
                'message' => 'Cette clé existe déjà'
            ]);

        $validator
            ->scalar('value')
            ->requirePresence('value', 'create')
            ->notEmptyString('value')
            ->add('value', 'validHour', [
                'rule' => function ($value, $context) {
                    $key = $context['data']['key'] ?? '';
                    if (in_array($key, ['grid_start_hour', 'grid_end_hour'])) {
                        $intVal = (int)$value;
                        return $intVal >= 0 && $intVal <= 23;
                    }
                    return true;
                },
                'message' => 'L\'heure doit être entre 0 et 23'
            ])
            ->add('value', 'validRange', [
                'rule' => function ($value, $context) {
                    $key = $context['data']['key'] ?? '';
                    if ($key === 'grid_end_hour') {
                        $startHour = $this->getValue('grid_start_hour', 0);
                        $endHour = (int)$value;
                        return $endHour > $startHour;
                    }
                    return true;
                },
                'message' => 'L\'heure de fin doit être supérieure à l\'heure de début'
            ]);

        $validator
            ->scalar('description')
            ->requirePresence('description', 'create')
            ->notEmptyString('description');

        $validator
            ->scalar('type')
            ->maxLength('type', 255)
            ->requirePresence('type', 'create')
            ->notEmptyString('type');

        return $validator;
    }

    /**
     * Récupère la valeur d'un paramètre par sa clé
     * 
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut si le paramètre n'existe pas
     * @return mixed La valeur du paramètre, castée selon son type
     */
    public function getValue(string $key, $default = null)
    {
        $setting = $this->find()
            ->where(['DisplaySettings.key' => $key])
            ->first();

        if (!$setting) {
            return $default;
        }

        // Caster la valeur selon le type
        switch ($setting->type) {
            case 'int':
                return (int)$setting->value;
            case 'boolean':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            case 'string':
            default:
                return $setting->value;
        }
    }
}
