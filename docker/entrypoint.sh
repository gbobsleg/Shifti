#!/bin/bash
set -euo pipefail

APP_DIR="/var/www/html"
cd "${APP_DIR}"

# Config locale CakePHP (DATABASE_URL via env)
if [ ! -f "${APP_DIR}/config/app_local.php" ]; then
  echo "[entrypoint] Création de config/app_local.php depuis l'exemple"
  cp "${APP_DIR}/config/app_local.example.php" "${APP_DIR}/config/app_local.php"
fi

# Répertoires runtime CakePHP
mkdir -p \
  "${APP_DIR}/tmp/cache/models" \
  "${APP_DIR}/tmp/cache/persistent" \
  "${APP_DIR}/tmp/cache/views" \
  "${APP_DIR}/tmp/sessions" \
  "${APP_DIR}/tmp/tests" \
  "${APP_DIR}/logs"

chown -R www-data:www-data "${APP_DIR}/tmp" "${APP_DIR}/logs"
chmod -R 775 "${APP_DIR}/tmp" "${APP_DIR}/logs"

# Attente MariaDB (si DB_HOST défini)
if [ -n "${DB_HOST:-}" ]; then
  echo "[entrypoint] Attente de MariaDB sur ${DB_HOST}:3306..."
  for i in $(seq 1 60); do
    if php -r "try { new PDO('mysql:host=${DB_HOST};port=3306', '${DB_USER:-planning}', '${DB_PASSWORD:-}'); echo 'ok'; } catch (Throwable \$e) { exit(1); }" 2>/dev/null; then
      echo "[entrypoint] MariaDB joignable"
      break
    fi
    if [ "$i" -eq 60 ]; then
      echo "[entrypoint] ERREUR: MariaDB indisponible après 60s" >&2
      exit 1
    fi
    sleep 1
  done
fi

echo "[entrypoint] Démarrage supervisord (nginx + php-fpm + solvers + worker)"
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
