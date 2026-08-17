#!/usr/bin/env sh
set -eu

DB_NAME="${MARIADB_DATABASE:-dockercart}"
DB_USER="${MARIADB_USER:-root}"
DB_PASSWORD="${MARIADB_PASSWORD:-}"
DB_PREFIX="${DB_PREFIX:-oc_}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
DOCKERCART_URL="${DOCKERCART_URL:-http://dockercart.local}"
DOCKERCART_SEED_MODE="${DOCKERCART_SEED_MODE:-demo}"
SEED_SQL="/opt/dockercart-seed/init.sql"
CLEAN_SQL="/opt/dockercart-seed/clean_demo.sql"

if [ "${DOCKERCART_URL%/}" = "${DOCKERCART_URL}" ]; then
  DOCKERCART_URL="${DOCKERCART_URL}/"
fi

sql_escape() {
  printf "%s" "$1" | sed "s/'/''/g"
}

if [ ! -f "${SEED_SQL}" ]; then
  echo "[dockercart-init] ERROR: Seed SQL not found at ${SEED_SQL}" >&2
  exit 1
fi

echo "[dockercart-init] Importing seed SQL into '${DB_NAME}' with prefix '${DB_PREFIX}'..."
if [ "${DB_PREFIX}" = "oc_" ]; then
  MYSQL_PWD="${DB_PASSWORD}" mariadb -u"${DB_USER}" "${DB_NAME}" < "${SEED_SQL}"
else
  MYSQL_PWD="${DB_PASSWORD}" sed "s/\`oc_/\`${DB_PREFIX}/g" "${SEED_SQL}" | mariadb -u"${DB_USER}" "${DB_NAME}"
fi

USER_TABLE_EXISTS="$(MYSQL_PWD="${DB_PASSWORD}" mariadb -N -B -u"${DB_USER}" "${DB_NAME}" -e "SHOW TABLES LIKE '${DB_PREFIX}user';" 2>/dev/null || true)"
SETTING_TABLE_EXISTS="$(MYSQL_PWD="${DB_PASSWORD}" mariadb -N -B -u"${DB_USER}" "${DB_NAME}" -e "SHOW TABLES LIKE '${DB_PREFIX}setting';" 2>/dev/null || true)"
PRODUCT_TABLE_EXISTS="$(MYSQL_PWD="${DB_PASSWORD}" mariadb -N -B -u"${DB_USER}" "${DB_NAME}" -e "SHOW TABLES LIKE '${DB_PREFIX}product';" 2>/dev/null || true)"

if [ -z "${USER_TABLE_EXISTS}" ] || [ -z "${SETTING_TABLE_EXISTS}" ]; then
  echo "[dockercart-init] WARNING: Required OpenCart tables are missing after seed import."
  echo "[dockercart-init] WARNING: Skipping clean step. Fill docker/mysql/init.sql with a full dump and reinitialize DB volume."
  exit 0
fi

# NOTE: Admin account and store settings are NOT written here. The seed dump
# (init.sql) carries the admin row with a PASSWORD_PLACEHOLDER sentinel (the
# real hash is stripped by `make dump-init`), and the runtime entrypoint's
# ensure_admin_password() replaces it with the hash derived from ADMIN_PASSWORD
# on every boot. initialize_database() is the single source of truth for the
# rest of the bootstrap settings (store URL, encryption key, seed marker).
# This avoids a duplicate SHA1 vs Argon2ID bootstrap that previously caused
# admin login to break on first-run race conditions.
#
# For custom admin credentials at first install, set ADMIN_USERNAME/ADMIN_PASSWORD
# in .env and let the entrypoint bootstrap the admin on first boot.

if [ -n "${PRODUCT_TABLE_EXISTS}" ]; then
  MYSQL_PWD="${DB_PASSWORD}" mariadb -u"${DB_USER}" "${DB_NAME}" -e "UPDATE \`${DB_PREFIX}product\` SET viewed = 0;" || true
fi

# Clean install mode: strip demo content after seeding.
# Only ever runs on a freshly initialized (empty) database — the seed import
# above is the only path that reaches this code. On existing databases the
# entrypoint's initialize_database() never runs the clean step.
if [ "${DOCKERCART_SEED_MODE}" = "clean" ]; then
  echo "[dockercart-init] DOCKERCART_SEED_MODE=clean — removing demo data..."
  if [ ! -f "${CLEAN_SQL}" ]; then
    echo "[dockercart-init] ERROR: Clean SQL not found at ${CLEAN_SQL}" >&2
    exit 1
  fi
  if [ "${DB_PREFIX}" = "oc_" ]; then
    MYSQL_PWD="${DB_PASSWORD}" mariadb -u"${DB_USER}" "${DB_NAME}" < "${CLEAN_SQL}"
  else
    MYSQL_PWD="${DB_PASSWORD}" sed "s/\`oc_/\`${DB_PREFIX}/g" "${CLEAN_SQL}" | mariadb -u"${DB_USER}" "${DB_NAME}"
  fi
  echo "[dockercart-init] Demo data removed."
fi

# Mark this database as seeded so a later accidental re-run never strips
# content from a store that already has real data.
MYSQL_PWD="${DB_PASSWORD}" mariadb -u"${DB_USER}" "${DB_NAME}" -e "
INSERT INTO \`${DB_PREFIX}setting\` (store_id, \`code\`, \`key\`, \`value\`, serialized)
SELECT 0, 'config', 'config_dockercart_seed_mode', '${DOCKERCART_SEED_MODE}', 0
WHERE NOT EXISTS (SELECT 1 FROM \`${DB_PREFIX}setting\` WHERE \`key\` = 'config_dockercart_seed_mode' AND store_id = 0);" || true

echo "[dockercart-init] Bootstrap finished."

# Гарантируем config_encryption (на случай, если bootstrap был пропущен)
MYSQL_PWD="${DB_PASSWORD}" mariadb -u"${DB_USER}" "${DB_NAME}" -e "
UPDATE \`${DB_PREFIX}setting\` SET \`value\` = REPLACE(UUID(), '-', '')
WHERE \`key\` = 'config_encryption' AND store_id = 0
  AND (\`value\` IS NULL OR \`value\` = '');" || true
