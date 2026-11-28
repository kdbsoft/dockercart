#!/bin/bash
# DockerCart — store URL resolution (single source of truth).
#
# The store URL is DERIVED from DOCKERCART_DOMAIN + the listen ports, because
# the setup wizard only asks for the domain. An explicit DOCKERCART_URL /
# DOCKERCART_HTTPS_URL still wins, with one exception: the .env.example
# placeholder (`dockercart.local`) never wins over a real DOCKERCART_DOMAIN.
# A leftover placeholder is exactly what broke a real deployment — the wizard
# wrote DOCKERCART_DOMAIN=store.example.com while .env still carried
# DOCKERCART_URL=http://dockercart.local, so the storefront emitted 62
# `http://dockercart.local` images on an HTTPS page (mixed content) and every
# generated link pointed at a host nobody can resolve.
#
# Same for the ports: DOCKERCART_HTTP_PORT / DOCKERCART_HTTPS_PORT are the
# *public* ports (80/443 are omitted from the URL).
#
# Sourced by docker/entrypoint.sh (inside the container), start.sh and
# health-check.sh (on the host). It only defines functions — nothing runs at
# source time, so the caller decides when to resolve.
#
# resolve_store_urls exports:
#   STORE_HTTP_URL     http URL, always with a trailing slash
#   STORE_HTTPS_URL    https URL, always with a trailing slash
#   STORE_SSL_ENABLED  true|false, DOCKERCART_SSL_ENABLED normalised
#   STORE_URLS_DERIVED 1 when the URLs came from the domain, 0 when explicit
#
# store_effective_url prints the URL the storefront is actually served on
# (https when SSL is enabled) — the one robots.txt/sitemap and mail links need.

# The template placeholder: a URL for this host is ignored whenever the
# configured domain names a different one.
STORE_URL_PLACEHOLDER_DOMAIN="dockercart.local"

# Extract the host part of a URL (empty when unparsable).
store_url_host() {
	local url="$1"

	printf '%s' "$url" | sed -nE 's#^[A-Za-z][A-Za-z0-9+.-]*://([^/:]+).*$#\1#p'
}

# Should an explicit URL be ignored in favour of the derived one?
store_url_is_placeholder() {
	local url="$1" domain="$2"

	[ -n "$url" ] || return 1
	[ -n "$domain" ] || return 1
	[ "$domain" != "$STORE_URL_PLACEHOLDER_DOMAIN" ] || return 1
	[ "$(store_url_host "$url")" = "$STORE_URL_PLACEHOLDER_DOMAIN" ]
}

resolve_store_urls() {
	local domain="${DOCKERCART_DOMAIN:-}"
	local http_port="${DOCKERCART_HTTP_PORT:-80}"
	local https_port="${DOCKERCART_HTTPS_PORT:-443}"
	local url_http="${DOCKERCART_URL:-}"
	local url_https="${DOCKERCART_HTTPS_URL:-}"

	[ -n "$domain" ] || domain="$STORE_URL_PLACEHOLDER_DOMAIN"

	if store_url_is_placeholder "$url_http" "$domain"; then
		url_http=""
	fi
	if store_url_is_placeholder "$url_https" "$domain"; then
		url_https=""
	fi

	STORE_URLS_DERIVED=0

	# Only one URL given? Both describe the same store, so take the host from
	# the explicit one instead of mixing hosts.
	if [ -z "$url_http" ] && [ -n "$url_https" ]; then
		local https_host
		https_host="$(store_url_host "$url_https")"
		[ -z "$https_host" ] || domain="$https_host"
	fi
	if [ -z "$url_https" ] && [ -n "$url_http" ]; then
		local http_host
		http_host="$(store_url_host "$url_http")"
		[ -z "$http_host" ] || domain="$http_host"
	fi

	if [ -z "$url_http" ]; then
		STORE_URLS_DERIVED=1
		url_http="http://${domain}"
		if [ "$http_port" != "80" ]; then
			url_http="${url_http}:${http_port}"
		fi
	fi

	if [ -z "$url_https" ]; then
		STORE_URLS_DERIVED=1
		url_https="https://${domain}"
		if [ "$https_port" != "443" ]; then
			url_https="${url_https}:${https_port}"
		fi
	fi

	# Trailing slash: every consumer concatenates paths onto it
	# (config_url . 'image/…', sitemap entries, admin/).
	STORE_HTTP_URL="${url_http%/}/"
	STORE_HTTPS_URL="${url_https%/}/"

	case "$(printf '%s' "${DOCKERCART_SSL_ENABLED:-false}" | tr '[:upper:]' '[:lower:]')" in
		true|yes|on|1) STORE_SSL_ENABLED=true ;;
		*) STORE_SSL_ENABLED=false ;;
	esac

	export STORE_HTTP_URL STORE_HTTPS_URL STORE_SSL_ENABLED STORE_URLS_DERIVED
}

# URL the store actually answers on: https when TLS is enabled, http otherwise.
store_effective_url() {
	if [ "${STORE_SSL_ENABLED:-false}" = "true" ]; then
		printf '%s' "$STORE_HTTPS_URL"
	else
		printf '%s' "$STORE_HTTP_URL"
	fi
}

# Rewrite robots.txt Sitemap lines that no longer match the store URL.
#
# ensure_robots_txt() seeds robots.txt exactly once (the admin owns the file
# afterwards, so operator edits survive restarts). Two kinds of stale line could
# therefore stick forever: a first boot that still ran with the .env.example
# placeholder baked
# "Sitemap: http://dockercart.local:8080/sitemap.xml" into the file, and a line
# written over http (or with a stale port) before TLS was switched on — the store
# then served https pages while its sitemap was advertised as http, i.e. every
# crawler was sent through a redirect or to a host nobody can resolve.
#
# A line is "ours" when its host is the placeholder host or the store's own host;
# it is rewritten to "<store URL>/sitemap.xml". A line pointing at any other host
# (a CDN, an alternate domain) is operator-chosen and left untouched. Nothing
# else in the file is touched.
#
# The same rule lives in Dockercart\Seo\Robots (admin save/display);
# tools/tests/cases/robots_sitemap.php keeps both implementations in parity.
#
# $1 robots.txt path, $2 effective store URL (no trailing slash needed).
# Returns 0 when the file was rewritten, 1 when there was nothing to do.
store_robots_fix_sitemap() {
	local file="${1:-}" url="${2:-}" host p_re s_re target stale tmp

	[ -f "$file" ] || return 1
	[ -n "$url" ] || return 1

	url="${url%/}"
	host="$(store_url_host "$url")"
	[ -n "$host" ] || return 1

	target="${url}/sitemap.xml"

	# grep -E patterns: only the dots of the hosts need escaping.
	p_re="${STORE_URL_PLACEHOLDER_DOMAIN//./\\.}"
	s_re="${host//./\\.}"

	# How many Sitemap lines are ours but do not already carry the store URL?
	stale="$(sed -nE 's/^[[:space:]]*[Ss]itemap:[[:space:]]*//p' "$file" \
		| grep -vxF "$target" \
		| sed -E 's|^[A-Za-z][A-Za-z0-9+.-]*://||' | cut -d/ -f1 | cut -d: -f1 \
		| tr '[:upper:]' '[:lower:]' \
		| grep -cxE "${p_re}|${s_re}" || true)"

	# Already canonical, or nothing of ours in the file: leave it alone (this also
	# keeps the file's mtime stable across restarts).
	[ "${stale:-0}" -gt 0 ] 2>/dev/null || return 1

	# Outside the webroot on purpose: the webroot is a bind mount shared with
	# nginx, a half-written robots.txt must never be servable.
	tmp="$(mktemp)" || return 1

	if ! sed -E "s#^([[:space:]]*[Ss]itemap:[[:space:]]*)https?://(${p_re}|${s_re})([:/][^[:space:]]*)?[[:space:]]*\$#\1${target}#" "$file" > "$tmp"; then
		rm -f "$tmp"

		return 1
	fi

	# Write through the existing inode: the host-side owner/group and the 0664
	# mode set by the entrypoint survive (sed -i would replace the file).
	if ! cat "$tmp" > "$file" 2>/dev/null; then
		rm -f "$tmp"
		echo "WARNING: could not rewrite the sitemap line in $file" >&2

		return 1
	fi

	rm -f "$tmp"

	return 0
}
