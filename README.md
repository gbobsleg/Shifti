# Shifti

Application de **planning** et de **prévision de charge** (CakePHP 5, solveurs Python OR-Tools / Prophet, Docker).

## Prérequis

- Docker + Docker Compose, **ou**
- PHP 8.1+, Composer, MariaDB/MySQL, et Python 3.10+ (services solveur / forecast)

## Démarrage rapide (Docker)

```bash
cp .env.docker.example .env
# Éditer .env : mots de passe, SECURITY_SALT, etc.

docker compose up -d --build
```

L’app écoute en local sur le port défini par `APP_HTTP_PORT` (défaut `8080`).

Migrations :

```bash
docker compose exec app bin/cake migrations migrate
```

## Développement local (WAMP)

1. `composer install`
2. Copier `config/app_local.example.php` → `config/app_local.php` et configurer la BDD
3. Lancer les services locaux : `scripts/dev/start_all_services.bat` (voir aussi `solver-python/README_SERVICES.md`)

## Déploiement production

Déploiement manuel via GitHub Actions (`Deploy production`) et `deploy/production.sh`.  
Configurer les secrets Actions (`VPS_HOST`, `VPS_USER`, `VPS_APP_PATH`, `VPS_SSH_KEY`) — **jamais** de secrets dans le dépôt.

## Structure

| Dossier | Rôle |
|---------|------|
| `src/`, `templates/`, `config/` | Application CakePHP |
| `solver-python/` | APIs FastAPI (activités fixes, couverture, rotation, Prophet) |
| `docker/`, `Dockerfile` | Image et runtime production |
| `deploy/` | Script de déploiement VPS |
| `scripts/dev/` | Lanceurs Windows (services Python + workers) |
| `tests/` | PHPUnit |

## Licence

MIT (voir `composer.json`).
