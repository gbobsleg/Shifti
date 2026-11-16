#!/usr/bin/env bash
# Anonymise nom, prénom, matricule et e-mail des agents.
# Ne pas appeler depuis deploy/production.sh.
#
# 1. Sauvegarde seule, aucun UPDATE :
#      bash deploy/anonymize-users.sh
#    Copier ~/shifti-identity-backup/current hors du VPS et vérifier SHA256SUMS.
#
# 2. Anonymisation, seulement après cette copie :
#      COPIED=1 bash deploy/anonymize-users.sh --apply
#
# Simon HOUSSIN n'est pas modifié. Les mots de passe non plus.
# Après succès, la copie restante sur le VPS est supprimée.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

log() { echo "[anonymize $(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"; }
die() { echo "[anonymize ERROR] $*" >&2; exit 1; }
abort_backup() {
  if [[ -d "$CURRENT" && ! -f "$CURRENT/SHA256SUMS" ]]; then
    rm -rf -- "$CURRENT"
  fi
  die "$@"
}

command -v docker >/dev/null || die "docker introuvable"
docker compose version >/dev/null 2>&1 || die "docker compose introuvable"
[[ -f docker-compose.yml ]] || die "Lancer ce script depuis le dépôt (deploy/anonymize-users.sh)"
[[ -f .env ]] || die ".env introuvable"

set -a
# shellcheck disable=SC1091
source .env
set +a

MYSQL_DATABASE="${MYSQL_DATABASE:-cake_planning}"
MYSQL_USER="${MYSQL_USER:-planning}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
[[ -n "$MYSQL_PASSWORD" ]] || die "MYSQL_PASSWORD vide dans .env"

OUT_ROOT="${ANON_OUT_DIR:-$HOME/shifti-identity-backup}"
CURRENT="$OUT_ROOT/current"
APPLY=0
if [[ "${1:-}" == "--apply" ]]; then
  APPLY=1
elif [[ -n "${1:-}" ]]; then
  die "Argument inconnu : $1"
fi

run_php() {
  local host_file="$1"
  local name status
  name="$(basename "$host_file")"
  docker compose cp "$host_file" "app:/tmp/$name" >/dev/null
  status=0
  docker compose exec -T \
    -e DB_HOST=db \
    -e DB_USER="$MYSQL_USER" \
    -e DB_PASSWORD="$MYSQL_PASSWORD" \
    -e DB_NAME="$MYSQL_DATABASE" \
    app php -d detect_unicode=0 "/tmp/$name" || status=$?
  docker compose exec -T app rm -f "/tmp/$name" >/dev/null || true
  return "$status"
}

write_export_php() {
  local dest="$1"
  cat > "$dest" <<'PHP'
<?php
declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
    (string)getenv('DB_USER'),
    (string)getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$rows = $pdo->query('SELECT id, first_name, last_name, user_code, email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$out = fopen('php://stdout', 'wb');
fputcsv($out, ['id', 'first_name', 'last_name', 'user_code', 'email']);
foreach ($rows as $row) {
    fputcsv($out, [$row['id'], $row['first_name'], $row['last_name'], $row['user_code'], $row['email']]);
}
fclose($out);
PHP
}

write_apply_php() {
  local dest="$1"
  cat > "$dest" <<'PHP'
<?php
declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
    (string)getenv('DB_USER'),
    (string)getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$rows = $pdo->query('SELECT id, first_name, last_name, user_code, email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$simon = [];
foreach ($rows as $row) {
    $first = mb_strtolower(trim((string)$row['first_name']));
    $last = mb_strtolower(trim((string)$row['last_name']));
    if ($first === 'simon' && $last === 'houssin') {
        $simon[] = $row;
    }
}
if (count($simon) !== 1) {
    fwrite(STDERR, 'Simon HOUSSIN introuvable ou en double (' . count($simon) . ")\n");
    exit(1);
}
$kept = $simon[0];
$keptId = (int)$kept['id'];

foreach ($rows as $row) {
    if ((int)$row['id'] === $keptId) {
        continue;
    }
    if (str_ends_with(strtolower((string)$row['email']), '@anonyme.fr')) {
        fwrite(STDERR, "Anonymisation déjà présente. Rien n'a été écrit.\n");
        exit(1);
    }
}

$plan = [];
foreach ($rows as $row) {
    $id = (int)$row['id'];
    if ($id === $keptId) {
        continue;
    }
    $digits = preg_replace('/\D/', '', (string)$row['user_code']) ?? '';
    $last = substr($digits, -3);
    $plan[] = ['id' => $id, 'suffix' => $last === '' ? (string)$id : $last];
}

$counts = [];
foreach ($plan as $item) {
    $counts[$item['suffix']] = ($counts[$item['suffix']] ?? 0) + 1;
}

$emails = [];
foreach ($rows as $row) {
    $emails[(int)$row['id']] = (string)$row['email'];
}

$ready = [];
foreach ($plan as $item) {
    $id = $item['id'];
    $suffix = $item['suffix'];
    if ($counts[$suffix] > 1) {
        $first = $suffix . '-' . $id;
        $code = substr($suffix . '-' . $id, 0, 11);
    } else {
        $first = $suffix;
        $code = substr($suffix, 0, 11);
    }
    if ($code === (string)$kept['user_code']) {
        $code = substr($suffix . '-' . $id, 0, 11);
    }
    $email = 'a' . $id . '@anonyme.fr';
    foreach ($emails as $otherId => $existing) {
        if ($otherId !== $id && strcasecmp($existing, $email) === 0) {
            fwrite(STDERR, "Collision d'e-mail pour l'id $id\n");
            exit(1);
        }
    }
    if ($first === '' || $code === '') {
        fwrite(STDERR, "Valeur vide pour l'id $id\n");
        exit(1);
    }
    $ready[] = ['id' => $id, 'first' => $first, 'code' => $code, 'email' => $email];
}

$seen = [];
foreach ($ready as $item) {
    if (isset($seen[$item['code']])) {
        fwrite(STDERR, 'Matricules anonymes encore en doublon : ' . $item['code'] . "\n");
        exit(1);
    }
    $seen[$item['code']] = true;
}
if ($ready === [] || count($ready) !== count($rows) - 1) {
    fwrite(STDERR, "Nombre de lignes inattendu\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $tmpMail = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
    $final = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, user_code = ?, email = ? WHERE id = ?');
    foreach ($ready as $item) {
        $tmpMail->execute(['tmp-' . $item['id'] . '@anonyme.fr', $item['id']]);
    }
    foreach ($ready as $item) {
        $final->execute([$item['first'], 'Agent', $item['code'], $item['email'], $item['id']]);
    }

    $check = $pdo->prepare('SELECT first_name, last_name, user_code, email FROM users WHERE id = ?');
    $check->execute([$keptId]);
    $now = $check->fetch(PDO::FETCH_ASSOC);
    if (!$now
        || $now['first_name'] !== $kept['first_name']
        || $now['last_name'] !== $kept['last_name']
        || $now['user_code'] !== $kept['user_code']
        || $now['email'] !== $kept['email']
    ) {
        throw new RuntimeException('Simon HOUSSIN a ete modifie');
    }
    $named = (int)$pdo->query(
        'SELECT COUNT(*) FROM users WHERE id <> ' . $keptId . " AND last_name <> 'Agent'"
    )->fetchColumn();
    if ($named !== 0) {
        throw new RuntimeException('Un agent a encore son nom');
    }
    $mails = (int)$pdo->query(
        'SELECT COUNT(*) FROM users WHERE id <> ' . $keptId . " AND email NOT LIKE '%@anonyme.fr'"
    )->fetchColumn();
    if ($mails !== 0) {
        throw new RuntimeException('Un agent a encore son e-mail');
    }
    $changed = (int)$pdo->query(
        'SELECT COUNT(*) FROM users WHERE id <> ' . $keptId . " AND last_name = 'Agent'"
    )->fetchColumn();
    if ($changed !== count($ready)) {
        throw new RuntimeException('Nombre de lignes modifiees inattendu');
    }
    $dupCodes = (int)$pdo->query(
        'SELECT COUNT(*) FROM (SELECT user_code FROM users WHERE id <> ' . $keptId . ' GROUP BY user_code HAVING COUNT(*) > 1) d'
    )->fetchColumn();
    if ($dupCodes !== 0) {
        throw new RuntimeException('Deux matricules anonymes se confondent');
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$sample = $ready[0];
echo 'Simon intact id=' . $keptId . "\n";
echo 'Modifies: ' . count($ready) . "\n";
echo 'Exemple id ' . $sample['id'] . ' -> Agent / ' . $sample['first'] . ' / ' . $sample['code'] . ' / ' . $sample['email'] . "\n";
PHP
}

if [[ "$APPLY" -eq 0 ]]; then
  if [[ -e "$CURRENT" ]]; then
    die "Sauvegarde déjà présente : $CURRENT — ne pas l'écraser."
  fi
  mkdir -p "$CURRENT"
  chmod 700 "$OUT_ROOT" "$CURRENT"

  log "Dump complet -> $CURRENT/full.sql.gz"
  if ! docker compose exec -T db \
    mariadb-dump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" \
    --single-transaction --routines --triggers \
    "$MYSQL_DATABASE" | gzip -c > "$CURRENT/full.sql.gz"; then
    abort_backup "Dump échoué"
  fi
  [[ -s "$CURRENT/full.sql.gz" ]] || abort_backup "Dump vide"
  gzip -t "$CURRENT/full.sql.gz" || abort_backup "Dump illisible"
  set +o pipefail
  gzip -dc "$CURRENT/full.sql.gz" | tail -n 1 | grep -q "Dump completed"
  dump_ok=$?
  set -o pipefail
  [[ "$dump_ok" -eq 0 ]] || abort_backup "Dump incomplet"

  log "Extrait users -> $CURRENT/users.csv"
  export_php="$(mktemp)"
  write_export_php "$export_php"
  status=0
  run_php "$export_php" > "$CURRENT/users.csv" || status=$?
  rm -f "$export_php"
  [[ "$status" -eq 0 ]] || abort_backup "Export CSV échoué"
  [[ -s "$CURRENT/users.csv" ]] || abort_backup "CSV vide"
  header="$(head -n 1 "$CURRENT/users.csv" | tr -d '\r')"
  [[ "$header" == "id,first_name,last_name,user_code,email" ]] || abort_backup "En-tête CSV inattendu"
  lines="$(wc -l < "$CURRENT/users.csv" | tr -d ' ')"
  [[ "$lines" -ge 2 ]] || abort_backup "CSV sans données"

  (
    cd "$CURRENT"
    sha256sum full.sql.gz users.csv > SHA256SUMS
  )
  chmod 600 "$CURRENT/full.sql.gz" "$CURRENT/users.csv" "$CURRENT/SHA256SUMS"
  log "Sauvegarde prête. Aucun nom n'a été modifié."
  log "Dossier : $CURRENT"
  log "Copiez ce dossier hors du VPS, vérifiez SHA256SUMS, puis seulement :"
  log "  COPIED=1 bash deploy/anonymize-users.sh --apply"
  exit 0
fi

[[ "${COPIED:-}" == "1" ]] || die "COPIED=1 manquant. Copie hors du VPS non confirmée. Aucun UPDATE."
[[ -d "$CURRENT" ]] || die "Dossier de sauvegarde introuvable : $CURRENT"
[[ -s "$CURRENT/full.sql.gz" && -s "$CURRENT/users.csv" && -s "$CURRENT/SHA256SUMS" ]] || die "Sauvegarde incomplète"
gzip -t "$CURRENT/full.sql.gz" || die "Dump illisible"
(
  cd "$CURRENT"
  sha256sum -c SHA256SUMS
) || die "Sommes SHA256 invalides"

log "Anonymisation"
apply_php="$(mktemp)"
write_apply_php "$apply_php"
status=0
run_php "$apply_php" || status=$?
rm -f "$apply_php"
[[ "$status" -eq 0 ]] || die "Anonymisation annulée, base inchangée"

[[ "$CURRENT" == */shifti-identity-backup/current ]] || die "Chemin de suppression refusé"
rm -rf -- "$CURRENT"
log "Anonymisation terminée. Copie locale du VPS supprimée."
log "La copie emportée hors du serveur est la seule. Ne la remettez ici que pour deploy/restore-users.sh."
