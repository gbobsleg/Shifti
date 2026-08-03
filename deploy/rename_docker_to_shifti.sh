#!/usr/bin/env bash
# Migration one-shot VPS : ressources Docker planning_new_* → shifti_*
# Prérequis : racine du clone, code déjà à jour (conteneurs/volumes shifti_* dans compose).
# Usage : bash deploy/rename_docker_to_shifti.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

log() { echo "[rename-docker $(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"; }
die() { echo "[rename-docker ERROR] $*" >&2; exit 1; }

[[ -f .env ]] || die ".env introuvable"
[[ -f docker-compose.yml ]] || die "docker-compose.yml introuvable"
command -v docker >/dev/null || die "docker introuvable"

set -a
# shellcheck disable=SC1091
source .env
set +a

MYSQL_DATABASE="${MYSQL_DATABASE:-cake_planning}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
[[ -n "$MYSQL_ROOT_PASSWORD" ]] || die "MYSQL_ROOT_PASSWORD vide"

OLD_DB_VOL="planning_new_db_data"
NEW_DB_VOL="shifti_db_data"
OLD_TMP_VOL="planning_new_app_tmp"
NEW_TMP_VOL="shifti_app_tmp"

mkdir -p "$HOME/backups"
BACKUP="$HOME/backups/pre_rename_docker_${MYSQL_DATABASE}_$(date -u +%Y%m%dT%H%M%SZ).sql.gz"

dump_db() {
  local container="$1"
  docker exec -i "$container" \
    mariadb-dump -uroot -p"$MYSQL_ROOT_PASSWORD" \
    --single-transaction --routines --triggers \
    "$MYSQL_DATABASE"
}

log "Backup BDD -> $BACKUP"
if docker ps --format '{{.Names}}' | grep -qx planning_db; then
  dump_db planning_db | gzip -c > "$BACKUP"
elif docker ps --format '{{.Names}}' | grep -qx shifti_db; then
  dump_db shifti_db | gzip -c > "$BACKUP"
elif docker volume inspect "$OLD_DB_VOL" >/dev/null 2>&1; then
  log "Aucun db running — démarrage temporaire sur $OLD_DB_VOL"
  docker rm -f shifti_tmp_db_backup 2>/dev/null || true
  docker run -d --name shifti_tmp_db_backup \
    -v "$OLD_DB_VOL:/var/lib/mysql" \
    -e "MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD" \
    mariadb:11.4 >/dev/null
  for i in $(seq 1 60); do
    if docker exec shifti_tmp_db_backup healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 \
      || docker exec shifti_tmp_db_backup mariadb-admin ping -uroot -p"$MYSQL_ROOT_PASSWORD" --silent >/dev/null 2>&1; then
      break
    fi
    [[ "$i" -eq 60 ]] && { docker logs shifti_tmp_db_backup || true; docker rm -f shifti_tmp_db_backup || true; die "Timeout db temporaire"; }
    sleep 2
  done
  dump_db shifti_tmp_db_backup | gzip -c > "$BACKUP"
  docker rm -f shifti_tmp_db_backup >/dev/null
elif docker volume inspect "$NEW_DB_VOL" >/dev/null 2>&1; then
  log "Aucun db running — démarrage temporaire sur $NEW_DB_VOL"
  docker rm -f shifti_tmp_db_backup 2>/dev/null || true
  docker run -d --name shifti_tmp_db_backup \
    -v "$NEW_DB_VOL:/var/lib/mysql" \
    -e "MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD" \
    mariadb:11.4 >/dev/null
  for i in $(seq 1 60); do
    if docker exec shifti_tmp_db_backup mariadb-admin ping -uroot -p"$MYSQL_ROOT_PASSWORD" --silent >/dev/null 2>&1; then
      break
    fi
    [[ "$i" -eq 60 ]] && { docker rm -f shifti_tmp_db_backup || true; die "Timeout db temporaire"; }
    sleep 2
  done
  dump_db shifti_tmp_db_backup | gzip -c > "$BACKUP"
  docker rm -f shifti_tmp_db_backup >/dev/null
else
  die "Aucun conteneur db ni volume BDD pour backup"
fi
[[ -s "$BACKUP" ]] || die "Backup vide"
log "Backup OK ($(du -h "$BACKUP" | awk '{print $1}'))"

log "Arrêt stack ancienne + courante"
# Anciens noms fixes (avant rename compose)
docker stop planning_app planning_db 2>/dev/null || true
docker rm planning_app planning_db 2>/dev/null || true
# Projet Compose historique
docker compose -p planning_new down --remove-orphans 2>/dev/null || true
# Projet courant (dossier / COMPOSE_PROJECT_NAME)
docker compose down --remove-orphans 2>/dev/null || true
# Adminer orphelin éventuel
while IFS= read -r c; do
  [[ -n "$c" ]] || continue
  docker rm -f "$c" 2>/dev/null || true
done < <(docker ps -a --format '{{.Names}}' | grep -E 'planning_new-adminer|shifti-adminer' || true)

copy_volume() {
  local from="$1" to="$2"
  if ! docker volume inspect "$from" >/dev/null 2>&1; then
    log "Volume source absent: $from — skip"
    return 0
  fi
  if docker volume inspect "$to" >/dev/null 2>&1; then
    # Si déjà peuplé (rejeu), ne pas écraser
    log "Volume cible existe déjà: $to — conservation"
    return 0
  fi
  log "Copie volume $from -> $to"
  docker volume create "$to" >/dev/null
  docker run --rm \
    -v "$from:/from:ro" \
    -v "$to:/to" \
    alpine:3.20 \
    sh -c 'cd /from && cp -a . /to/'
  log "Copie OK: $to"
}

copy_volume "$OLD_DB_VOL" "$NEW_DB_VOL"
copy_volume "$OLD_TMP_VOL" "$NEW_TMP_VOL"

[[ "$(docker volume inspect "$NEW_DB_VOL" >/dev/null 2>&1 && echo ok || true)" == "ok" ]] \
  || die "Volume $NEW_DB_VOL manquant après copie"

log "Mise à jour COMPOSE_PROJECT_NAME=shifti dans .env"
if grep -qE '^COMPOSE_PROJECT_NAME=' .env; then
  sed -i -E 's/^COMPOSE_PROJECT_NAME=.*/COMPOSE_PROJECT_NAME=shifti/' .env
else
  printf '\nCOMPOSE_PROJECT_NAME=shifti\n' >> .env
fi

log "docker compose up -d --build"
docker compose build app
docker compose up -d

log "Attente healthy shifti_app"
for i in $(seq 1 90); do
  status="$(docker inspect -f '{{.State.Health.Status}}' shifti_app 2>/dev/null || echo starting)"
  if [[ "$status" == "healthy" ]]; then
    log "app healthy"
    break
  fi
  if [[ "$i" -eq 90 ]]; then
    docker compose ps || true
    docker compose logs --tail=80 app || true
    die "Timeout shifti_app (status=$status)"
  fi
  sleep 2
done

PORT="${APP_HTTP_PORT:-8080}"
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "http://127.0.0.1:${PORT}/" || true)"
case "$HTTP_CODE" in
  200|301|302|303|307|308) log "Smoke OK (HTTP $HTTP_CODE)" ;;
  *) die "Smoke KO (HTTP ${HTTP_CODE:-none})" ;;
esac

log "Suppression anciens volumes planning_new_* (si libres)"
for v in planning_new_db_data planning_new_app_tmp planning_new_app_logs; do
  if docker volume inspect "$v" >/dev/null 2>&1; then
    if docker volume rm "$v" 2>/dev/null; then
      log "Supprimé: $v"
    else
      log "Conservé (encore référencé?): $v"
    fi
  fi
done

log "État final"
docker compose ps
docker volume ls | grep -E 'shifti_|planning_new_|DRIVER|VOLUME' || true
log "Rename Docker terminé — commit=$(git rev-parse --short HEAD) backup=$BACKUP"
