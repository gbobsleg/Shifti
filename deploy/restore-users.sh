#!/usr/bin/env bash
# Réécrit nom, prénom, matricule et e-mail depuis le CSV de sauvegarde.
# Le planning n'est pas touché. Ne pas appeler depuis deploy/production.sh.
#
#   RESTORE=1 bash deploy/restore-users.sh /chemin/users.csv
#
# Le CSV est supprimé après un retour réussi. Le dump complet, s'il est à côté, reste.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

log() { echo "[restore $(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"; }
die() { echo "[restore ERROR] $*" >&2; exit 1; }

[[ "${RESTORE:-}" == "1" ]] || die "RESTORE=1 manquant. Aucune écriture."
CSV_PATH="${1:-}"
[[ -n "$CSV_PATH" && -f "$CSV_PATH" ]] || die "CSV introuvable"
[[ -s "$CSV_PATH" ]] || die "CSV vide"

command -v docker >/dev/null || die "docker introuvable"
docker compose version >/dev/null 2>&1 || die "docker compose introuvable"
[[ -f .env ]] || die ".env introuvable"

set -a
# shellcheck disable=SC1091
source .env
set +a

MYSQL_DATABASE="${MYSQL_DATABASE:-cake_planning}"
MYSQL_USER="${MYSQL_USER:-planning}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
[[ -n "$MYSQL_PASSWORD" ]] || die "MYSQL_PASSWORD vide dans .env"

host_csv="$(mktemp)"
cp "$CSV_PATH" "$host_csv"
php_file="$(mktemp)"
cat > "$php_file" <<'PHP'
<?php
declare(strict_types=1);

$csvPath = getenv('CSV_PATH');
if ($csvPath === false || $csvPath === '') {
    fwrite(STDERR, "CSV_PATH manquant\n");
    exit(1);
}
$handle = fopen($csvPath, 'rb');
if ($handle === false) {
    fwrite(STDERR, "CSV illisible\n");
    exit(1);
}
$header = fgetcsv($handle);
$expected = ['id', 'first_name', 'last_name', 'user_code', 'email'];
if ($header !== $expected) {
    fwrite(STDERR, "En-tête CSV inattendu\n");
    exit(1);
}
$rows = [];
while (($line = fgetcsv($handle)) !== false) {
    if ($line === [null] || $line === false || (count($line) === 1 && $line[0] === null)) {
        continue;
    }
    if (count($line) !== 5) {
        fwrite(STDERR, "Ligne CSV invalide\n");
        exit(1);
    }
    $rows[] = [
        'id' => (int)$line[0],
        'first_name' => (string)$line[1],
        'last_name' => (string)$line[2],
        'user_code' => (string)$line[3],
        'email' => (string)$line[4],
    ];
}
fclose($handle);
if ($rows === []) {
    fwrite(STDERR, "CSV sans données\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
    (string)getenv('DB_USER'),
    (string)getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$ids = array_map(static fn (array $row): int => $row['id'], $rows);
if (count($ids) !== count(array_unique($ids))) {
    fwrite(STDERR, "Id en double dans le CSV\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $tmp = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
    $final = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, user_code = ?, email = ? WHERE id = ?');
    foreach ($rows as $row) {
        $tmp->execute(['tmp-restore-' . $row['id'] . '@anonyme.fr', $row['id']]);
    }
    foreach ($rows as $row) {
        $final->execute([$row['first_name'], $row['last_name'], $row['user_code'], $row['email'], $row['id']]);
    }
    $check = $pdo->prepare('SELECT first_name, last_name, user_code, email FROM users WHERE id = ?');
    foreach ($rows as $row) {
        $check->execute([$row['id']]);
        $now = $check->fetch(PDO::FETCH_ASSOC);
        if (!$now
            || $now['first_name'] !== $row['first_name']
            || $now['last_name'] !== $row['last_name']
            || $now['user_code'] !== $row['user_code']
            || $now['email'] !== $row['email']
        ) {
            throw new RuntimeException('Contrôle échoué pour l\'id ' . $row['id']);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo 'Restaures: ' . count($rows) . "\n";
PHP

docker compose cp "$php_file" app:/tmp/shifti-restore-users.php
docker compose cp "$host_csv" app:/tmp/shifti-restore-users.csv
rm -f "$php_file" "$host_csv"

set +e
docker compose exec -T \
  -e DB_HOST=db \
  -e DB_USER="$MYSQL_USER" \
  -e DB_PASSWORD="$MYSQL_PASSWORD" \
  -e DB_NAME="$MYSQL_DATABASE" \
  -e CSV_PATH=/tmp/shifti-restore-users.csv \
  app php -d detect_unicode=0 /tmp/shifti-restore-users.php
status=$?
set -e
docker compose exec -T app rm -f /tmp/shifti-restore-users.php /tmp/shifti-restore-users.csv
[[ "$status" -eq 0 ]] || die "Restauration annulée, base inchangée"

rm -f -- "$CSV_PATH"
log "Restauration terminée. CSV supprimé : $CSV_PATH"
