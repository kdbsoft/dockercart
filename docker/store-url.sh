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

# Rewrite a robots.txt whose Sitemap line points at the placeholder host.
#
# ensure_robots_txt() seeds robots.txt exactly once (the admin owns the file
# afterwards, so operator edits survive restarts). A first boot that still ran
# with the .env.example placeholder therefore baked
# "Sitemap: http://dockercart.local:8080/sitemap.xml" into the file, and no
# later fix could reach it: search engines were pointed at a host no visitor can
# resolve while the storefront itself was fine. This repairs that one line —
# only that line, and only for a placeholder host (a real host chosen by the
# operator, e.g. a CDN, is left untouched).
#
# The same rule lives in Dockercart\Seo\Robots (admin save/display);
# tools/tests/cases/robots_sitemap.php keeps both implementations in parity.
#
# $1 robots.txt path, $2 store URL (no trailing slash needed).
# Returns 0 when the file was rewritten, 1 when there was nothing to do.
store_robots_fix_sitemap() {
	local file="${1:-}" url="${2:-}" host host_re tmp

	[ -f "$file" ] || return 1
	[ -n "$url" ] || return 1

	url="${url%/}"
	host="$(store_url_host "$url")"

	# A local install *is* the placeholder host: nothing to correct.
	[ -n "$host" ] || return 1
	[ "$host" != "$STORE_URL_PLACEHOLDER_DOMAIN" ] || return 1

	# Only Sitemap lines are considered (one directive per line).
	if ! sed -nE 's/^[[:space:]]*Sitemap:[[:space:]]*//Ip' "$file" \
		| cut -d/ -f3 | cut -d: -f1 | tr '[:upper:]' '[:lower:]' \
		| grep -qx "$STORE_URL_PLACEHOLDER_DOMAIN"; then
		return 1
	fi

	host_re="${STORE_URL_PLACEHOLDER_DOMAIN//./\\.}"
	# Outside the webroot on purpose: the webroot is a bind mount shared with
	# nginx, a half-written robots.txt must never be servable.
	tmp="$(mktemp)" || return 1

	if ! sed -E "s|^([[:space:]]*Sitemap:[[:space:]]*)https?://${host_re}([:/][^[:space:]]*)?[[:space:]]*\$|\1${url}/sitemap.xml|I" "$file" > "$tmp"; then
		rm -f "$tmp"

		return 1
	fi

	# Write through the existing inode: the host-side owner/group and the 0664
	# mode set by the entrypoint survive (sed -i would replace the file).
	if ! cat "$tmp" > "$file" 2>/dev/null; then
		rm -f "$tmp"
		echo "WARNING: could not rewrite the placeholder sitemap in $file" >&2

		return 1
	fi

	rm -f "$tmp"

	return 0
}
