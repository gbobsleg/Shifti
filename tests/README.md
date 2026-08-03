# Tests PHPUnit

```bash
composer install
# Configurer une BDD de test dans config/app_local.php (clé Datasources.test)
composer test
```

Sans base `test_*` accessible, le bootstrap Migrations échoue au démarrage de PHPUnit.
Les tests existants sont hérités du squelette CakePHP / modules métier ; à étendre au fil des features.
