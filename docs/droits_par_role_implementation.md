---
name: Implementation droits par role
overview: "Plan d'implémentation, découpé en lots, de la refonte des droits par rôle : branche dédiée, rôle Planificateur, capacités centralisées, périmètre par site, correction des failles, grille et menus. Ce plan est rédigé pour être exécuté par un autre modèle, sans interprétation."
todos:
  - id: lot0-branche
    content: "Lot 0 : créer la branche feature/droits-par-role depuis main"
    status: pending
  - id: lot1-roles
    content: "Lot 1 : migration roles.code, rôle Planificateur, nouvelles priorités"
    status: pending
  - id: lot2-services
    content: "Lot 2 : Capability, RoleCapabilities, PermissionService, PerimeterService, AbstractCapabilityPolicy + tests"
    status: pending
  - id: lot3-policies
    content: "Lot 3 : réécrire les 24 policies, WfmSettings policy, authorize manquants"
    status: pending
  - id: lot4-strict
    content: "Lot 4 : supprimer RequestPolicy, mode strict, AuthorizationCoverageTest"
    status: pending
  - id: lot5-users
    content: "Lot 5 : restriction d'attribution des rôles et de gestion des comptes supérieurs"
    status: pending
  - id: lot6-grille
    content: "Lot 6 : périmètre site et alertes, Grids::needSeries, canEditGrid, palette légende"
    status: pending
  - id: lot7-menus
    content: "Lot 7 : nav-sidebar, error_state, Pages/admin par capacités"
    status: pending
  - id: lot8-recette
    content: "Lot 8 : grep de contrôle, tests, cs-check, recette manuelle par rôle"
    status: pending
isProject: false
---

# Implémentation : droits par rôle

## Règles d'exécution (à lire avant de coder)

- Travailler lot par lot, dans l'ordre. **À la fin de chaque lot : un commit (message en français), puis s'arrêter, résumer et attendre la validation de l'utilisateur.**
- Ne modifier que les fichiers listés dans le lot. Si un autre fichier semble devoir changer, s'arrêter et demander.
- Ne jamais comparer des identifiants de rôle (`role_id === 1`, `<= 2`). Toute décision passe par `PermissionService::has()` ou par le périmètre.
- Ne pas committer `tmp/` (fichiers de cache non suivis déjà présents).
- Ne pas changer le comportement métier d'une action : on change seulement **qui** peut l'exécuter.
- Conventions du projet : `declare(strict_types=1);`, commentaires et messages en français, nouvelles migrations en `BaseMigration` avec `up()` et `down()` (comme [config/Migrations/20261007120000_AddCreatedByUserIdToRanges.php](config/Migrations/20261007120000_AddCreatedByUserIdToRanges.php)).

## Contexte vérifié

- CakePHP 5, `cakephp/authorization` 3. [src/Application.php](src/Application.php), lignes 146 à 178 : un `MapResolver` associe chaque `App\Resource\XxxResource` à `App\Policy\XxxPolicy`. Les vues appellent `$identity->can('action', new XxxResource())`. **Cette structure est conservée** : seules les règles internes des policies changent.
- L'identité (session) est l'entité `User` sans association : elle contient `role_id` et `site_id`, mais pas `role`.
- Rôles en base : `1 Administrateur (priority 1)`, `2 Manager (priority 2)`, `3 Utilisateur (priority 3)`. La colonne `roles.priority` existe déjà.
- [src/Controller/AppController.php](src/Controller/AppController.php), lignes 80 à 99 : `authorize($request, 'access')` + [src/Policy/RequestPolicy.php](src/Policy/RequestPolicy.php) acceptent tout utilisateur connecté. C'est ce qui masque les actions non protégées.
- Tests : PHPUnit, base `test_myapp` via `Migrator`. Aucun test d'autorisation n'existe. Les nouveaux tests ne doivent **pas** dépendre de la base (injection de la table des rôles).

## Stratégie de tests automatisés

Trois niveaux de tests, tous dans `tests/TestCase/Authorization/` sauf mention contraire :

- **Tests unitaires, sans base** : la matrice des rôles (`PermissionServiceTest`), le périmètre (`PerimeterServiceTest`) et la correspondance entre chaque méthode de policy et sa capacité (`PolicyCapabilityMapTest`).
- **Test statique** : chaque action de contrôleur appelle `authorize()` ou `skipAuthorization()` (`AuthorizationCoverageTest`).
- **Tests d'intégration HTTP, base `test_myapp`** (elle existe déjà ; le `Migrator` de `tests/bootstrap.php` y applique les migrations) : un utilisateur par rôle est connecté par session, puis on vérifie les codes de réponse.

Règles :

- Le **test d'un refus** vérifie `assertResponseCode(403)`. Il est fiable, car le refus survient avant le rendu de la page.
- Le **test d'une autorisation** vérifie au niveau unitaire que la policy accepte. Les tests HTTP d'autorisation sont réservés aux actions légères (JSON, redirections) pour lesquelles les fixtures suffisent. Ne pas tester par HTTP le rendu complet des pages lourdes (grille, admin) : il faudrait des dizaines de fixtures.
- **Critère de fin de chaque lot** : `vendor/bin/phpunit` donne au moins le même nombre de tests réussis qu'avant (comparaison avec la référence du lot 0), et tous les nouveaux tests passent.

Socle commun, à créer au lot 2 :

- Fixtures `tests/Fixture/RegionsFixture.php` (1 région), `SitesFixture.php` (sites 1 et 2, région 1), `RolesFixture.php` et `UsersFixture.php`.
  - `RolesFixture` : ids 1 à 4, avec `code` et `priority` identiques à la migration du lot 1 (admin 1/10, manager 2/30, agent 3/40, planificateur 4/20).
  - `UsersFixture` : un utilisateur par rôle (ids 1 admin, 2 manager, 3 agent sur le site 1, 4 planificateur), plus un agent id 5 sur le site 2. Colonnes obligatoires : `user_code`, `last_name`, `first_name`, `email`, `password` (n'importe quel hash), `role_id`, `site_id`.
- Trait `tests/TestCase/Authorization/LoginAsTrait.php` avec `loginAs(int $userId): void` :
  - il charge l'utilisateur via `fetchTable('Users')->get($userId)` ;
  - il appelle `$this->session(['Auth' => $user])`, la clé lue par `Authentication.Session` ;
  - il active aussi `$this->enableCsrfToken()` et `$this->enableSecurityToken()` pour les requêtes POST.
- Chaque test d'intégration déclare `protected array $fixtures = ['app.Regions', 'app.Sites', 'app.Roles', 'app.Users']`, plus les fixtures propres au test.

## Lot 0 : branche et référence des tests

```bash
git checkout main
git pull
git checkout -b feature/droits-par-role
vendor/bin/phpunit
```

- Noter dans le résumé du lot le nombre de tests, d'échecs et d'erreurs. C'est la référence pour la suite.
- Ne corriger aucun test existant à ce stade.

## Lot 1 : rôles (migration)

- Créer `config/Migrations/20261009100000_AddCodeToRolesAndPlanificateur.php` :
  - `up()` :
    - ajouter `roles.code` (string 32, nullable, index unique) ;
    - renseigner les codes : `UPDATE roles SET code='admin' WHERE id=1`, `'manager'` pour l'id 2, `'agent'` pour l'id 3 ;
    - insérer `name='Planificateur', code='planificateur'` ;
    - renuméroter `priority` : admin 10, planificateur 20, manager 30, agent 40.
  - `down()` : supprimer la ligne `planificateur`, remettre les priorités 1, 2 et 3, supprimer la colonne `code`.
- [src/Model/Entity/Role.php](src/Model/Entity/Role.php) : ajouter `@property string|null $code` et **ne pas** rendre `code` accessible en masse (`'code' => false`). Le code n'est pas éditable depuis l'interface.
- Lancer `bin/cake migrations migrate`, puis vérifier avec `SELECT id, name, code, priority FROM roles`.

## Lot 2 : capacités et services (aucun changement de comportement)

Nouveaux fichiers :

- `src/Authorization/Capability.php` : `final class` avec une constante string par capacité, plus `public static function all(): array`. Liste exacte :
  - `PLANNING_CONSULTER='planning.consulter'`, `PLANNING_CONSULTER_TOUS_SITES='planning.consulter_tous_sites'`, `PLANNING_INDICATEURS='planning.indicateurs'`, `PLANNING_MODIFIER='planning.modifier'`, `PLANNING_GENERER='planning.generer'`, `PLANNING_PUBLIER='planning.publier'`, `PLAGES_GERER='plages.gerer'`
  - `IMPORT_PLANNING='import.planning'`, `HISTORIQUE_IMPORTER='historique.importer'`, `HISTORIQUE_CONSULTER='historique.consulter'`
  - `PREVISION_GERER='prevision.gerer'`, `PREVISION_PUBLIER='prevision.publier'`
  - `OFFRES_GERER='offres.gerer'`, `OFFRES_OPTIMISER='offres.optimiser'`
  - `ABSENCES_GERER='absences.gerer'`, `TELETRAVAIL_GERER='teletravail.gerer'`, `ALERTES_GERER='alertes.gerer'`, `ALERTES_CONSULTER_TOUTES='alertes.consulter_toutes'`
  - `UTILISATEURS_GERER='utilisateurs.gerer'`
  - `REF_ROLES`, `REF_SITES`, `REF_REGIONS`, `REF_COMPETENCES`, `REF_DISPONIBILITES`, `REF_AFFICHAGE`, `REF_CORRESPONDANCES`, `REF_WFM`, `REF_ROTATIONS`, `REF_ACTIVITES_FIXES` (valeurs `referentiels.roles`, etc.)
  - `JOBS_SUPERVISER='jobs.superviser'`, `ADMINISTRATION_ACCEDER='administration.acceder'`
- `src/Authorization/RoleCapabilities.php` : `final class` avec `public static function forCode(?string $code): array`. Un code inconnu ou null donne `[]`. La matrice :
  - `agent` : `PLANNING_CONSULTER`
  - `manager` : `PLANNING_CONSULTER`, `PLANNING_CONSULTER_TOUS_SITES`, `PLANNING_INDICATEURS`, `PLANNING_MODIFIER`, `HISTORIQUE_CONSULTER`, `ABSENCES_GERER`, `TELETRAVAIL_GERER`, `ALERTES_GERER`, `ALERTES_CONSULTER_TOUTES`, `UTILISATEURS_GERER`, `ADMINISTRATION_ACCEDER`
  - `planificateur` : manager plus `PLANNING_GENERER`, `PLANNING_PUBLIER`, `IMPORT_PLANNING`, `PREVISION_GERER`, `PREVISION_PUBLIER`, `JOBS_SUPERVISER`, `REF_ROTATIONS`, `REF_ACTIVITES_FIXES`
  - `admin` : `Capability::all()`
- `src/Service/Authorization/PermissionService.php` :
  - constructeur `__construct(?array $roles = null)`. `$roles` est au format `[roleId => ['code' => string, 'priority' => int]]`. S'il vaut null, il est chargé depuis la table `Roles` (`fetchTable('Roles')->find()->select(['id','code','priority'])`), avec un cache statique par requête.
  - `has(mixed $identity, string $capability): bool` : lit `role_id` via `$identity->get('role_id')`, ou via `getOriginalData()` en repli, comme le `roleId()` actuel.
  - `rolePriority(mixed $identity): ?int` ;
  - `assignableRoleIds(mixed $identity): array` : les rôles dont `priority >= priorité de l'acteur` ;
  - `canManageUser(mixed $identity, int $targetRoleId): bool` : vrai si la priorité de la cible est supérieure ou égale à celle de l'acteur.
- `src/Service/Authorization/PerimeterService.php` (utilise `PermissionService`) :
  - `visibleSiteIds(mixed $identity): ?array` : `null` signifie tous les sites si l'utilisateur a `PLANNING_CONSULTER_TOUS_SITES` ; sinon `[site_id de l'identité]`, ou `[]` si `site_id` est vide.
  - `visibleAlertPriorities(mixed $identity): ?array` : `null` avec `ALERTES_CONSULTER_TOUTES`, sinon `[3]`.
- `src/Policy/AbstractCapabilityPolicy.php` : classe abstraite.
  - `__construct(?PermissionService $permissions = null)` : le paramètre est optionnel, parce que le `MapResolver` instancie les policies sans argument.
  - `protected function has(IdentityInterface $identity, string $capability): bool` : sans service injecté, une instance de `PermissionService` est créée paresseusement.
  - L'injection permet aux tests de passer un service construit avec une table des rôles en mémoire.

Tests (sans base) dans `tests/TestCase/Authorization/` :

- `PermissionServiceTest` : un data provider qui couvre chaque couple rôle et capacité de la matrice. Il teste aussi `assignableRoleIds` (le manager obtient manager et agent, pas planificateur ni admin), `canManageUser`, et le cas d'un `role_id` inconnu, qui ne donne aucune capacité.
- `PerimeterServiceTest` : un agent du site 5 donne `[5]`, un manager donne `null` ; pour les alertes, un agent donne `[3]` et un manager `null`.
- `RoleCapabilitiesTest` :
  - chaque valeur des listes de `RoleCapabilities` existe dans `Capability::all()`, ce qui détecte les fautes de frappe ;
  - le planificateur contient toutes les capacités du manager.
- Créer le socle commun décrit dans la stratégie de tests (4 fixtures et `LoginAsTrait`), plus un test de fumée, `LoginAsTraitTest`. Il se connecte avec l'admin, appelle `GET /users/account` et vérifie `assertResponseOk()`. Il valide que la connexion par session fonctionne avant que les lots suivants s'en servent.
- Lancer `vendor/bin/phpunit tests/TestCase/Authorization`.

## Lot 3 : réécriture des policies

Chaque policy `extends AbstractCapabilityPolicy`. Supprimer les méthodes privées `roleId()`. Correspondance exacte :

- `AbsencesPolicy` (toutes) : `ABSENCES_GERER`
- `AlertsPolicy` (toutes) : `ALERTES_GERER`
- `BackgroundJobsPolicy` (index, status, cancelOptuna) : `JOBS_SUPERVISER`
- `DisplaySettingsPolicy` : `REF_AFFICHAGE`
- `ExcelUploadsPolicy` (upload, preview, process) : `IMPORT_PLANNING`
- `FixedActivityRulesPolicy` : `REF_ACTIVITES_FIXES`
- `ForecastScenariosPolicy` : index, add, run, status, view, delete et edit donnent `PREVISION_GERER` ; publish et unpublish donnent `PREVISION_PUBLIER`
- `GridsPolicy` :
  - ajouter `canIndex`, `canGetUsersBySite` et `canNeedSeries` ;
  - index et getUsersBySite : `PLANNING_CONSULTER` ;
  - plannedSeries et needSeries : `PLANNING_INDICATEURS` ;
  - add, dayHistory et restoreDayHistory : `PLANNING_MODIFIER`.
- `HistoricalDataPolicy` : import donne `HISTORIQUE_IMPORTER` ; visualize et getData donnent `HISTORIQUE_CONSULTER`
- `OfferGroupsPolicy` : `OFFRES_GERER`
- `OffersPolicy` : index, view, add, edit et delete donnent `OFFRES_GERER` ; toutes les méthodes `tune*` donnent `OFFRES_OPTIMISER`
- `PagesPolicy::canAdmin` : `ADMINISTRATION_ACCEDER` (supprimer le test sur le nom du rôle)
- `PlanningEventMappingsPolicy` : `REF_CORRESPONDANCES`
- `PlanningGenerationJobsPolicy` :
  - ajouter `canRetry` et `canClearDraft` ;
  - index, add, edit, view, delete, status, report, equityReport, draft, saveDraft et retry : `PLANNING_GENERER` ;
  - publish et clearDraft : `PLANNING_PUBLIER`.
- `RangesPolicy` : `PLAGES_GERER`
- `RegionsPolicy` : `REF_REGIONS`
- `RemoteWorkPolicy` :
  - index, configure, ajaxGetUserSettings, addDay et delete : `TELETRAVAIL_GERER` ;
  - supprimer `canManageDays`, qui n'est jamais utilisé.
- `RolesPolicy` : `REF_ROLES`
- `RotationRulesPolicy` : `REF_ROTATIONS`
- `SchedulesPolicy::canGenerate` : `PLANNING_GENERER`
- `SitesPolicy` : `REF_SITES`
- `SkillsPolicy` : `REF_COMPETENCES`
- `UserAvailabilitiesPolicy` : `REF_DISPONIBILITES`
- `UsersPolicy` (toutes) : `UTILISATEURS_GERER`
- Nouveau `src/Resource/WfmSettingsResource.php` (copier le modèle d'une Resource existante) et `src/Policy/WfmSettingsPolicy.php` : index, view, add, edit et delete donnent `REF_WFM`. Enregistrer l'association dans [src/Application.php](src/Application.php).

Appels `authorize()` à ajouter ou corriger dans les contrôleurs. L'appel doit être **la première instruction** de l'action :

- `WfmSettingsController` : les 5 actions, avec `authorize(new WfmSettingsResource(), '<action>')`.
- `ForecastScenariosController::edit` : `'edit'`.
- `GridsController::index` : `'index'` ; `getUsersBySite` : `'getUsersBySite'`.
- `PlanningGenerationJobsController` : `retry` passe de `'delete'` à `'retry'` (ligne 174 environ) ; `clearDraft` passe de `'publish'` à `'clearDraft'` (ligne 2016 environ).
- `PagesController::display` : si `$path[0] === 'admin'`, `authorize(new PagesResource(), 'admin')` ; pour toute autre page, `skipAuthorization()` puis `throw new NotFoundException()`. `home.php` est le gabarit CakePHP par défaut et n'est pas utilisé.

Tests du lot 3 :

- `PolicyCapabilityMapTest` (unitaire, sans base) :
  - un data provider liste chaque triplet `[classe de policy, méthode, capacité attendue]`, en reprenant exactement la correspondance ci-dessus ;
  - pour chaque triplet et chacun des 4 rôles, le test vérifie que la policy répond `RoleCapabilities::forCode($code)` contient la capacité attendue ;
  - un second test parcourt par réflexion toutes les méthodes `can*` de `src/Policy/*Policy.php` et échoue si l'une d'elles est absente du data provider, pour qu'aucune méthode ne soit oubliée.
- `AccessDeniedTest` (intégration) : un data provider `[userId, méthode HTTP, URL]` vérifie chaque fois `assertResponseCode(403)`. Ce sont les anciennes failles et les nouveaux retraits de droits :
  - agent : `GET /wfm-settings`, `GET /forecast-scenarios/edit/1`, `GET /pages/admin`, `GET /alerts`, `GET /users` ;
  - manager : `GET /wfm-settings`, `GET /forecast-scenarios`, `GET /planning-generation-jobs`, `GET /excel-uploads/upload`, `GET /background-jobs`, `GET /rotation-rules`, `GET /fixed-activity-rules`, `GET /planning-event-mappings`, `GET /ranges` ;
  - planificateur : `GET /wfm-settings`, `GET /ranges`, `GET /offers`.
- Mettre à jour [tests/TestCase/Controller/PagesControllerTest.php](tests/TestCase/Controller/PagesControllerTest.php) :
  - `/pages/home` renvoie 404, même connecté ;
  - `/pages/admin` sans session redirige vers `/users/login` ;
  - agent connecté sur `/pages/admin` : 403.

## Lot 4 : mode strict (une action oubliée échoue au lieu d'être ouverte)

- [src/Controller/AppController.php](src/Controller/AppController.php) : supprimer `$this->Authorization->authorize($this->request, 'access')`. Conserver les `skipAuthorization()` existants (connexion, déconnexion, DebugKit, utilisateur non authentifié).
- `UsersController::account` et `changePassword` : ajouter `$this->Authorization->skipAuthorization();` en première instruction. Ces actions sont limitées à soi-même.
- Supprimer `src/Policy/RequestPolicy.php` et son entrée dans `MapResolver`.
- Nouveau test `tests/TestCase/Authorization/AuthorizationCoverageTest.php` :
  - il parcourt par réflexion chaque classe de `src/Controller/*Controller.php`, sauf `AppController` et `ErrorController` ;
  - pour chaque méthode publique déclarée dans la classe, hors `initialize`, `beforeFilter`, `beforeRender` et `afterFilter`, il vérifie que son code source contient `Authorization->authorize(` ou `skipAuthorization(` ;
  - le code source est extrait entre `getStartLine()` et `getEndLine()` ;
  - les actions `login` et `logout` sont exemptées, avec la raison en commentaire.
- Vérification manuelle : ouvrir chaque page du menu Administration avec un compte admin. Aucune `AuthorizationRequiredException` ne doit apparaître.

## Lot 5 : utilisateurs et attribution des rôles

Fichier : [src/Controller/UsersController.php](src/Controller/UsersController.php).

- `add` et `edit` : la liste `$roles` (lignes 510 et 814 environ) est filtrée par `PermissionService::assignableRoleIds($identity)`.
- `add` et `edit` en POST : avant `patchEntity`, si `role_id` est présent et absent de `assignableRoleIds`, lever `ForbiddenException('Rôle non attribuable')`.
- `edit`, `delete` et `deleteContract` : charger l'utilisateur cible et, si `!canManageUser($identity, $target->role_id)`, lever `ForbiddenException`.
  - Pour `deleteContract`, la cible est l'utilisateur propriétaire du contrat.
  - Dans `edit`, ce contrôle doit précéder toute écriture, y compris `Skills->deleteAll` (ligne 618 environ).
- `index` et `view` : le bouton Modifier n'est affiché que si `canManageUser`. Passer à la vue un tableau `$manageableRoleIds`, et le lire dans [templates/Users/index.php](templates/Users/index.php) et [templates/Users/view.php](templates/Users/view.php).
- Test unitaire `tests/TestCase/Authorization/RoleAssignmentTest.php`, au niveau du service : manager vers admin refusé, manager vers manager accepté, planificateur vers planificateur accepté.
- Test d'intégration `tests/TestCase/Controller/UsersRoleAssignmentTest.php` (fixtures de base) :
  - manager, `POST /users/add` avec `role_id = 1` : 403, et le nombre d'utilisateurs est inchangé ;
  - manager, `POST /users/edit/3` (agent) avec `role_id = 4` (planificateur) : 403, et le `role_id` de l'utilisateur 3 est inchangé en base ;
  - manager, `GET /users/edit/1` (admin) : 403 ;
  - manager, `POST /users/delete/1` : 403, et l'utilisateur 1 existe toujours ;
  - planificateur, `POST /users/edit/3` avec `role_id = 4` : pas de 403, et le `role_id` vaut 4 en base. Si le formulaire exige d'autres champs, les inclure dans les données POST (lire `templates/Users/edit.php`).

## Lot 6 : grille

### Périmètre (fichiers [src/Controller/GridsController.php](src/Controller/GridsController.php) et [src/Model/Table/UsersTable.php](src/Model/Table/UsersTable.php))

- `UsersTable::findThisDay` : si `$params['allowed_site_ids']` est un tableau non vide, ajouter `where(['Users.site_id IN' => ...])`.
- `GridsController::index`, juste après la lecture de `site_id`, `user_id` et `offer_id` (lignes 270 à 272 environ) :
  - `$siteIds = PerimeterService::visibleSiteIds($identity)` ;
  - si `$siteIds !== null` :
    - injecter `$params['allowed_site_ids'] = $siteIds` ;
    - si le `site_id` demandé n'est pas dans la liste, forcer `site_id` (et `$params['site_id']`) sur `$siteIds[0]` ;
    - filtrer `$sites_list` et `$users_list` (ligne 286 environ) sur ces sites.
- `GridsController::getUsersBySite` : même règle, appliquée avant le `where` des lignes 156 à 158.
- Alertes (lignes 285 à 304) : remplacer le test `role_id === 3` par `visibleAlertPriorities()`. Si la valeur n'est pas null, ajouter `where(['priority IN' => ...])`.

### Ligne besoin

- Nouvelle action `GridsController::needSeries($scenarioId)`, en GET et JSON :
  - première instruction : `authorize(new GridsResource(), 'needSeries')` ;
  - paramètres `offer_id`, `date` et `type`, comme `ForecastScenariosController::series` (lignes 242 à 254) ;
  - vérifier qu'une ligne `ForecastScenarioPublications` existe pour ce `scenario_id` à cette `date`, sinon lever `NotFoundException` ;
  - renvoyer le même JSON en appelant `WfmScenarioService::getSeries` ;
  - même réglage de vue JSON que `plannedSeries`.
- [templates/Grids/index.php](templates/Grids/index.php), ligne 334 : `data-need-base` lit `$needSeriesBaseUrl`, qui vaut par défaut `Grids::needSeries`.
- `PlanningGenerationJobsController::draft` (lignes 2193 à 2200) passe `needSeriesBaseUrl` vers `ForecastScenarios::series`. Le brouillon utilise un scénario non publié, et le planificateur a `PREVISION_GERER`.

### Droits d'édition dans le template

- `GridsController::index` envoie à la vue `canEditGrid` (`has(PLANNING_MODIFIER)`), `canLoadSeries` (`has(PLANNING_INDICATEURS)`), `canAlertsAdd` et `canAlertsDelete` (`has(ALERTES_GERER)`).
- `PlanningGenerationJobsController::draft` envoie `canEditGrid = has(PLANNING_GENERER)` et `canLoadSeries = true`.
- [templates/Grids/index.php](templates/Grids/index.php), lignes 33 à 40 : supprimer le calcul local par `$can(...)`. Utiliser à la place `$canEditGrid ?? false`, `$canLoadSeries ?? false`, etc. Remplacer chaque `$canSavePlanning` par `$canEditGrid`.
- Palette [templates/element/grids/_paint_rail.php](templates/element/grids/_paint_rail.php) : nouveau paramètre `interactive` (bool), passé à `$canEditGrid` depuis `index.php` ligne 243. Si `false` :
  - classe `is-legend` sur `<aside>` ;
  - chaque pastille est un `<div class="grids-rail-swatch">` (sans la classe `offerColor` et sans `type="button"`).
  - CSS dans `webroot/css/grids/rail.css` : `.grids-rail.is-legend .grids-rail-swatch { cursor: default; }`. Ne pas toucher aux styles « selected ».

### Tests du lot 6

- `tests/TestCase/Model/Table/UsersTableThisDayTest.php` : `findThisDay` avec `allowed_site_ids = [1]` ne renvoie que des utilisateurs du site 1. Ajouter les fixtures nécessaires au finder (lire `UsersTable::findThisDay` pour les tables jointes).
- `tests/TestCase/Controller/GridsPerimeterTest.php` (intégration) :
  - agent 3 (site 1), `GET /grids/get-users-by-site.json?site_id=2` : la réponse ne contient aucun utilisateur du site 2 ;
  - agent 3, sans `site_id` : la réponse ne contient que des utilisateurs du site 1 ;
  - manager, `site_id=2` : la réponse contient l'utilisateur 5.
  - Vérifier l'URL exacte dans `config/routes.php` et `templates/element/grids-search-form.php`, ligne 188.
- `tests/TestCase/Controller/GridsNeedSeriesTest.php` (intégration), avec les fixtures `ForecastScenarios` et `ForecastScenarioPublications` à créer, sur le modèle des colonnes des migrations correspondantes :
  - agent : 403 ;
  - manager, scénario non publié à la date demandée : 404 ;
  - manager, scénario publié : pas de 403 ni de 404.
- `PerimeterServiceTest` : ajouter le cas de l'agent sans `site_id`, qui donne `[]`. La grille doit alors être vide, pas complète.

## Lot 7 : menus et page d'administration

- [templates/element/nav-sidebar.php](templates/element/nav-sidebar.php), lignes 24 à 114 : supprimer `$roleId`.
  - Le lien Administration s'affiche si `can('admin', new PagesResource())`.
  - Le badge des jobs s'affiche si `can('status', new BackgroundJobsResource())`.
- [templates/element/error_state.php](templates/element/error_state.php) : même condition pour le lien Administration.
- [templates/Pages/admin.php](templates/Pages/admin.php), lignes 135 à 269 : supprimer `$isAdmin` et `$isManager`.
  - Chaque tuile s'affiche si `can('index', new XxxResource())`, avec trois exceptions : l'import CSV teste `can('import', HistoricalDataResource)`, la visualisation teste `can('visualize', HistoricalDataResource)`, et la tuile « Test 1 jour » teste `can('generate', SchedulesResource)`.
  - La tuile Paramètres WFM utilise `WfmSettingsResource`.
  - Une section n'est affichée que si au moins une de ses tuiles est visible.
  - Le message « aucun contenu » s'affiche si aucune section n'est visible.
- `PagesController::loadAdminServicesHealth` (lignes 86 à 92) : remplacer le test `in_array($roleId, [1, 2])` par `has(Capability::JOBS_SUPERVISER)`.

## Lot 8 : nettoyage et recette

- Recherche de contrôle, avec pour résultat attendu aucune occurrence ailleurs que dans des filtres de recherche (`Users`, `Skills`, `UserAvailabilities`) :

```bash
rg "role_id\s*===|roleId|<= 2|=== 1|=== 2|=== 3" src/Policy src/Controller templates
```

- `vendor/bin/phpunit` (suite complète) : tous les nouveaux tests passent, et les échecs restants sont exactement ceux de la référence du lot 0.
- `composer cs-check` sur les fichiers modifiés.
- Recette manuelle : créer avec le compte admin 4 utilisateurs de test (un par rôle, l'agent sur un site précis), puis vérifier :
  - **Agent** :
    - grille limitée à son site, même avec `?site_id=` d'un autre site ;
    - alertes de priorité 3 seulement ;
    - pas de lignes besoin et réel, pas d'enregistrement, palette affichée en légende ;
    - `/pages/admin`, `/wfm-settings` et `/forecast-scenarios/edit/1` renvoient 403.
  - **Manager** :
    - grille de tous les sites, indicateurs visibles (la ligne besoin s'affiche bien), modification possible ;
    - accès à Absences, Télétravail (suppression comprise), Alertes, Utilisateurs et à la visualisation de l'historique ;
    - 403 sur prévisions, générations, import Excel, jobs, rotations, activités fixes et correspondances ;
    - impossible d'attribuer le rôle Planificateur ou Admin, ou de modifier un admin.
  - **Planificateur** : le manager, plus prévisions, générations, publication, import Excel, jobs, rotations et activités fixes ; modification du brouillon dans la grille embarquée.
  - **Admin** : tout fonctionne comme avant.

## Hors périmètre (ne pas faire)

- Droits par personne, interface d'édition des capacités, plusieurs rôles par utilisateur, périmètre multi-sites configurable : le code est seulement préparé pour ces évolutions (`allowed_site_ids`, `PerimeterService`).
- Revérification des droits dans les workers en ligne de commande.