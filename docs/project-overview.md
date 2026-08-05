# Shifti — Vue d’ensemble du projet

Shifti est une application **open source** (licence MIT) de **gestion de la force de travail (WFM)** pour le **planning** et la **prévision de charge**. Les planificateurs génèrent des plannings multi-passes (activités fixes → couverture → rotations), gèrent offres / compétences / sites, et pilotent des scénarios de prévision Prophet avec tuning Optuna optionnel.

**Langue du dépôt :** français. L’UI produit, la documentation, les messages utilisateur, et en règle générale les commentaires / messages de commit du projet sont en français. Les identifiants techniques (classes, endpoints, clés JSON) restent en anglais / snake_case comme dans le code existant.

## Stack

| Couche | Technologie |
|--------|-------------|
| App | PHP ≥8.1 (Docker : 8.3-FPM), **CakePHP 5.1** |
| Auth | CakePHP Authentication + Authorization (Policies) |
| BDD | **MariaDB 11.4** |
| Solveurs | Python FastAPI + **OR-Tools** (`solver-python/`, port 8000) |
| Prévision | FastAPI + **Prophet** (port 8001), worker Optuna |
| UI | Templates CakePHP serveur, Bootstrap-UI, plugin Ajax |
| Runtime | Nginx + Supervisord dans un conteneur `app` (php-fpm, solveurs, workers Cake) |

Locale / fuseau par défaut : **français / Europe/Paris**.

## Carte des dossiers

| Chemin | Rôle |
|--------|------|
| `src/` | Controllers, Models, Services, Policies, Commands, Resources |
| `templates/` | UI rendue côté serveur |
| `config/` | Config app, routes, Migrations (~70), Seeds |
| `webroot/` | Assets CSS/JS statiques |
| `solver-python/` | FastAPI solveurs + Prophet + worker Optuna |
| `docker/`, `Dockerfile` | Image et runtime |
| `deploy/` | Scripts de déploiement VPS |
| `scripts/dev/` | Lanceurs Windows (Python + workers Cake) |
| `tests/` | PHPUnit |
| `docs/` | Documentation projet (agents et humains) |

## Modèle d’exécution

```text
Navigateur → Nginx → CakePHP (php-fpm)
                          │
                          ├── MariaDB
                          ├── HTTP → solveur Python :8000 (OR-Tools)
                          ├── HTTP → Prophet :8001
                          └── Arrière-plan : cake-worker, cake-forecast-worker,
                              worker tuning Prophet, tickers Cake (Supervisord)
```

## Workflow local canonique

Préférer Docker Compose plutôt qu’inventer un Make/npm.

```bash
cp .env.docker.example .env
docker compose up -d --build
docker compose exec app bin/cake migrations migrate
```

Port HTTP : `APP_HTTP_PORT` (défaut `8080`).

### Contrôles qualité

| Objectif | Commande |
|----------|----------|
| Tests | `composer test` |
| CS check | `composer cs-check` |
| CS fix | `composer cs-fix` |
| Tout | `composer check` |

### WAMP / Windows local

1. `composer install`
2. Copier `config/app_local.example.php` → `config/app_local.php`
3. Démarrer via `scripts/dev/start_all_services.bat` (voir aussi `solver-python/README_SERVICES.md`)

### Production

GitHub Actions `Deploy production` + `deploy/production.sh`. Les secrets restent dans Actions — jamais dans le dépôt.

## Docs liées

- [Glossaire domaine](domain-model.md)
- [Contrats solveur (PHP ↔ Python)](solver-contracts.md)
- [Feature groupes d’offres](feature_groupes_offres.md)
- [README services Python](../solver-python/README_SERVICES.md)
- [README racine](../README.md)
