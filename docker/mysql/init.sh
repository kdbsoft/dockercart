#!/usr/bin/env sh
set -eu

DB_NAME="${MARIADB_DATABASE:-${MYSQL_DATABASE:-dockercart}}"
DB_USER="${MARIADB_USER:-${MYSQL_USER:-root}}"
DB_PASSWORD="${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-}}"
DB_PREFIX="${DB_PREFIX:-oc_}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
DOCKERCART_URL="${DOCKERCART_URL:-http://dockercart.local}"
SEED_SQL="/opt/dockercart-seed/init.sql"

if [ "${DOCKERCART_URL%/}" = "${DOCKERCART_URL}" ]; then
  DOCKERCART_URL="${DOCKERCART_URL}/"
fi

sql_escape() {
  printf "%s" "$1" | sed "s/'/''/g"
}

ADMIN_USERNAME_ESCAPED="$(sql_escape "${ADMIN_USERNAME}")"
ADMIN_PASSWORD_ESCAPED="$(sql_escape "${ADMIN_PASSWORD}")"
ADMIN_EMAIL_ESCAPED="$(sql_escape "${ADMIN_EMAIL}")"
DOCKERCART_URL_ESCAPED="$(sql_escape "${DOCKERCART_URL}")"

if [ ! -f "${SEED_SQL}" ]; then
  echo "[dockercart-init] ERROR: Seed SQL not found at ${SEED_SQL}" >&2
  exit 1
fi

echo "[dockercart-init] Importing seed SQL into '${DB_NAME}' with prefix '${DB_PREFIX}'..."
if [ "${DB_PREFIX}" = "oc_" ]; then
  mariadb -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" < "${SEED_SQL}"
else
  sed "s/\`oc_/\`${DB_PREFIX}/g" "${SEED_SQL}" | mariadb -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}"
fi

USER_TABLE_EXISTS="$(mariadb -N -B -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "SHOW TABLES LIKE '${DB_PREFIX}user';" 2>/dev/null || true)"
SETTING_TABLE_EXISTS="$(mariadb -N -B -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "SHOW TABLES LIKE '${DB_PREFIX}setting';" 2>/dev/null || true)"
API_TABLE_EXISTS="$(mariadb -N -B -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "SHOW TABLES LIKE '${DB_PREFIX}api';" 2>/dev/null || true)"
PRODUCT_TABLE_EXISTS="$(mariadb -N -B -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "SHOW TABLES LIKE '${DB_PREFIX}product';" 2>/dev/null || true)"

if [ -z "${USER_TABLE_EXISTS}" ] || [ -z "${SETTING_TABLE_EXISTS}" ] || [ -z "${API_TABLE_EXISTS}" ]; then
  echo "[dockercart-init] WARNING: Required OpenCart tables are missing after seed import."
  echo "[dockercart-init] WARNING: Skipping admin/settings bootstrap. Fill docker/mysql/init.sql with a full dump and reinitialize DB volume."
  exit 0
fi

echo "[dockercart-init] Applying DockerCart bootstrap settings and admin account..."
mariadb -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" <<SQL
SET NAMES utf8mb4;
SET @salt = 'dockercart';

DELETE FROM \`${DB_PREFIX}user\`
WHERE user_id = 1;

INSERT INTO \`${DB_PREFIX}user\`
  (user_id, user_group_id, username, salt, password, firstname, lastname, email, image, code, ip, status, date_added)
VALUES
  (
    1,
    1,
    '${ADMIN_USERNAME_ESCAPED}',
    @salt,
    SHA1(CONCAT(@salt, SHA1(CONCAT(@salt, SHA1('${ADMIN_PASSWORD_ESCAPED}'))))),
    'DockerCart',
    'Admin',
    '${ADMIN_EMAIL_ESCAPED}',
    '',
    '',
    '',
    1,
    NOW()
  );

DELETE FROM \`${DB_PREFIX}setting\`
WHERE \`key\` IN ('config_email', 'config_url', 'config_ssl', 'config_encryption', 'config_api_id');

INSERT INTO \`${DB_PREFIX}setting\` (store_id, \`code\`, \`key\`, \`value\`, serialized) VALUES
  (0, 'config', 'config_email', '${ADMIN_EMAIL_ESCAPED}', 0),
  (0, 'config', 'config_url', '${DOCKERCART_URL_ESCAPED}', 0),
  (0, 'config', 'config_ssl', '${DOCKERCART_URL_ESCAPED}', 0),
  (0, 'config', 'config_encryption', REPLACE(UUID(), '-', ''), 0);

DELETE FROM \`${DB_PREFIX}api\` WHERE username = 'Default';
INSERT INTO \`${DB_PREFIX}api\` (username, \`key\`, status, date_added, date_modified)
VALUES ('Default', REPLACE(UUID(), '-', ''), 1, NOW(), NOW());

SET @api_id = LAST_INSERT_ID();
INSERT INTO \`${DB_PREFIX}setting\` (store_id, \`code\`, \`key\`, \`value\`, serialized)
VALUES (0, 'config', 'config_api_id', @api_id, 0);
SQL

if [ -n "${PRODUCT_TABLE_EXISTS}" ]; then
  mariadb -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "UPDATE \`${DB_PREFIX}product\` SET viewed = 0;" || true
fi

echo "[dockercart-init] Bootstrap finished."
