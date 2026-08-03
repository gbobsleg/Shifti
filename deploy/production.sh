#!/usr/bin/env bash
# Deploiement production — VPS Docker Compose (shifti)
# Usage : depuis la racine du clone, en user docker :
#   bash deploy/production.sh
# Variables optionnelles :
#   DEPLOY_BRANCH (defaut: main)
#   SKIP_GIT=1          — ne pas git pull (si deja fait par le caller)
#   SKIP_BUILD=1        — ne pas rebuild l'image app
#   KEEP_BACKUPS=10     — nombre de dumps a conserver

set -euo pipefail

DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
KEEP_BACKUPS="${KEEP_BACKUPS:-10}"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

log() { echo "[deploy $(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"; }
die() { echo "[deploy ERROR] $*" >&2; exit 1; }

log "Racine: $ROOT_DIR"

command -v git >/dev/null || die "git introuvable"
command -v docker >/dev/null || die "docker introuvable"
docker compose version >/dev/null 2>&1 || die "docker compose introuvable"
[[ -f docker-compose.yml ]] || die "docker-compose.yml introuvable"
[[ -f .env ]] || die ".env introuvable (ne pas committer ; present uniquement sur le VPS)"

# Charger MYSQL_* / APP_HTTP_PORT depuis .env
set -a
# shellcheck disable=SC1091
source .env
set +a

MYSQL_DATABASE="${MYSQL_DATABASE:-cake_planning}"
MYSQL_USER="${MYSQL_USER:-planning}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
APP_HTTP_PORT="${APP_HTTP_PORT:-8080}"

[[ -n "$MYSQL_PASSWORD" ]] || die "MYSQL_PASSWORD vide dans .env"

# ---------------------------------------------------------------------------
# 1. Git
# ---------------------------------------------------------------------------
if [[ "${SKIP_GIT:-0}" != "1" ]]; then
  log "Git: fetch + ff-only $DEPLOY_BRANCH"
  if [[ -n "$(git status --porcelain)" ]]; then
    die "Working tree sale — commit/stash sur le VPS avant deploy"
  fi
  git fetch origin "$DEPLOY_BRANCH"
  git checkout "$DEPLOY_BRANCH"
  git pull --ff-only "origin" "$DEPLOY_BRANCH"
fi

log "Commit deploye: $(git rev-parse --short HEAD) — $(git log -1 --pretty=%s)"

# ---------------------------------------------------------------------------
# 2. Backup BDD
# ---------------------------------------------------------------------------
mkdir -p backups
BACKUP_FILE="backups/db_${MYSQL_DATABASE}_$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
log "Backup BDD -> $BACKUP_FILE"
# MariaDB 11 : binaire = mariadb-dump (pas mysqldump)
docker compose exec -T db \
  mariadb-dump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" \
  --single-transaction --routines --triggers \
  "$MYSQL_DATABASE" | gzip -c > "$BACKUP_FILE"
[[ -s "$BACKUP_FILE" ]] || die "Backup vide ou echoue"
log "Backup OK ($(du -h "$BACKUP_FILE" | awk '{print $1}'))"

# Rotation des dumps
mapfile -t OLD_BACKUPS < <(ls -1t backups/db_"${MYSQL_DATABASE}"_*.sql.gz 2>/dev/null || true)
if ((${#OLD_BACKUPS[@]} > KEEP_BACKUPS)); then
  for f in "${OLD_BACKUPS[@]:KEEP_BACKUPS}"; do
    log "Suppression ancien backup: $f"
    rm -f -- "$f"
  done
fi

# ---------------------------------------------------------------------------
# 3. Rebuild + up app (db inchange, volume persistant)
# ---------------------------------------------------------------------------
if [[ "${SKIP_BUILD:-0}" != "1" ]]; then
  log "docker compose build app"
  docker compose build app
  log "docker compose up -d app"
  docker compose up -d app
else
  log "SKIP_BUILD=1 — pas de rebuild"
fi

log "Attente sante conteneur app..."
for i in $(seq 1 60); do
  status="$(docker inspect -f '{{.State.Health.Status}}' planning_app 2>/dev/null || echo starting)"
  if [[ "$status" == "healthy" ]]; then
    log "app healthy"
    break
  fi
  if [[ "$i" -eq 60 ]]; then
    die "Timeout: planning_app pas healthy (status=$status)"
  fi
  sleep 2
done

# ---------------------------------------------------------------------------
# 4. Migrations + cache
# ---------------------------------------------------------------------------
log "Migrations CakePHP"
docker compose exec -T app php bin/cake.php migrations migrate

log "Clear cache"
docker compose exec -T app php bin/cake.php cache clear_all

# ---------------------------------------------------------------------------
# 5. Smoke check
# ---------------------------------------------------------------------------
SMOKE_URL="http://127.0.0.1:${APP_HTTP_PORT}/"
log "Smoke: $SMOKE_URL"
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$SMOKE_URL" || true)"
case "$HTTP_CODE" in
  200|301|302|303|307|308) log "Smoke OK (HTTP $HTTP_CODE)" ;;
  *) die "Smoke KO (HTTP ${HTTP_CODE:-none})" ;;
esac

log "Deploiement termine — $(git rev-parse HEAD)"
