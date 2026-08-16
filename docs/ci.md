# CI GitHub Actions (V1)

La CI vérifie la qualité du code PHP, exécute la suite PHPUnit et valide que l’image Docker démarre correctement avec les solveurs Python.

**Workflow :** [`.github/workflows/ci.yml`](../.github/workflows/ci.yml)

**Déclencheurs :** `pull_request` et `push` sur `main`.

**Concurrence :** un seul run actif par branche (`ci-${{ github.ref }}`) ; les runs plus récents annulent les précédents.

## Jobs

Les trois jobs s’exécutent **en parallèle**.

| Job | Rôle | Durée indicative |
|-----|------|------------------|
| `quality-php` | PHPCS via `composer cs-check` (étapes « Format / style » et « Lint » — même outil en V1) | ~2–5 min |
| `test-php` | PHPUnit avec MariaDB 11.4 en service | ~5–15 min |
| `build-and-smoke` | `docker compose build`, stack `db` + `app`, migrations, unittest Python, curls HTTP | ~15–60 min |

### `quality-php`

- PHP 8.3, extensions CakePHP (mbstring, intl, pdo_mysql, etc.)
- `composer install` puis `composer cs-check`

### `test-php`

- Service MariaDB 11.4 (`mariadb:11.4`) avec healthcheck
- `DATABASE_TEST_URL` pointe vers `127.0.0.1:3306` (service Actions)
- `SECURITY_SALT` factice ; `config/app_local.php` copié depuis l’exemple
- `composer test` — les migrations de test sont appliquées par `tests/bootstrap.php` (Migrator)

### `build-and-smoke`

1. Génère `.env` depuis [`.env.ci.example`](../.env.ci.example) (secrets factices, pas de VPS)
2. Compose avec override CI : `COMPOSE_FILE=docker-compose.yml:docker-compose.ci.yml` — isole conteneurs (`shifti_ci_*`), volumes et réseau des noms figés du stack local (utile sur runners self-hosted partagés)
3. `docker compose build app` puis `docker compose up -d --wait --wait-timeout 600 db app` — attend le HEALTHCHECK MariaDB **et** Nginx (image `app`, `start_period` 60 s)
4. Migrations CakePHP
5. Attente solveurs Python Supervisord (ports 8000 / 8001 — hors HEALTHCHECK Docker)
6. Unittest : `tests.test_offer_groups_coverage` dans `/var/www/html/solver-python`
7. Smoke HTTP : page d’accueil (2xx/3xx), `/health` OR-Tools et Prophet
8. `docker compose down -v` (toujours, même en cas d’échec)

## Lancer localement (équivalent partiel)

```bash
# Qualité
composer install
composer cs-check

# Tests (MariaDB locale requise)
export DATABASE_TEST_URL="mysql://user:pass@127.0.0.1:3306/test_shifti?encoding=utf8mb4&timezone=UTC&cacheMetadata=true&quoteIdentifiers=false&persistent=false"
export SECURITY_SALT="dev_salt"
cp config/app_local.example.php config/app_local.php
composer test

# Smoke Docker (comme en CI)
cp .env.ci.example .env
export COMPOSE_FILE=docker-compose.yml:docker-compose.ci.yml
docker compose build app
docker compose up -d --wait --wait-timeout 600 db app
docker compose exec -T app php bin/cake.php migrations migrate
docker compose exec -T app bash -c 'cd /var/www/html/solver-python && /opt/venv/bin/python -m unittest tests.test_offer_groups_coverage -v'
APP_HTTP_PORT="$(grep '^APP_HTTP_PORT=' .env | cut -d= -f2)"
curl -s -o /dev/null -w "%{http_code}\n" "http://127.0.0.1:${APP_HTTP_PORT}/"
docker compose down -v
```

## Lire un échec

| Job en échec | Pistes |
|--------------|--------|
| `quality-php` | Sortie PHPCS : fichier, ligne, règle PSR-12 / CakePHP. Corriger avec `composer cs-fix` si applicable. |
| `test-php` | Logs PHPUnit ; vérifier connexion BDD (`DATABASE_TEST_URL`), migrations, fixtures. |
| `build-and-smoke` | Logs `docker compose logs app` / `db` dans l’artifact Actions ; timeout fréquent sur le **premier** build (OR-Tools / Prophet). Vérifier health curls 8000/8001. |

## Hors périmètre V1

- **Déploiement VPS** : inchangé (`deploy-production.yml`, `deploy/**`)
- **Analyse statique** : pas de PHPStan, Psalm, Ruff, mypy
- **Smoke métier** : pas de scénario OfferGroups bout-en-bout via l’UI ou l’API Cake
- **Protection de branche** : à configurer manuellement sur GitHub (exiger la CI verte avant merge)
- **Secrets VPS** : non requis pour la CI

## Variables d’environnement CI

Voir [`.env.ci.example`](../.env.ci.example). Aligné sur [`.env.docker.example`](../.env.docker.example) avec des valeurs factices dédiées à Actions.

Override Compose CI : [`docker-compose.ci.yml`](../docker-compose.ci.yml) (utilisé via `COMPOSE_FILE`, pas besoin de le mettre dans `.env`).
