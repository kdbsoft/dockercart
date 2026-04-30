#!/bin/bash
#
# DockerCart custom entrypoint for Manticore Search.
#
# Replaces the official docker-entrypoint.sh at /usr/local/bin/docker-entrypoint.sh.
#
# What this does:
#   1. Creates /var/lib/manticore/data (needed for binlog_path).
#   2. Fixes ownership of Manticore directories (chown to 'manticore' user).
#   3. Drops privileges to 'manticore' via gosu before launching searchd.
#
# In DockerCart the entire /var/lib/manticore is backed by a tmpfs mount,
# so this init is minimal — just ensure the data/ subdirectory exists and
# correct permissions are set.

set -e

# Manticore 15.x does not auto-create /var/lib/manticore/data for binlog.
# The tmpfs mount creates /var/lib/manticore but not sub-directories.
mkdir -p /var/lib/manticore/data

# When running as root, chown everything to the 'manticore' user.
# This matches the pattern used by the official image's entrypoint.
if [ "$(id -u)" = '0' ]; then
    find /var/lib/manticore /var/log/manticore /var/run/manticore \
        \! -user manticore -exec chown manticore:manticore '{}' + 2>/dev/null || true
    exec gosu manticore "$0" "$@"
fi

# Non-root execution: just run the supplied CMD (searchd ...)
exec "$@"
