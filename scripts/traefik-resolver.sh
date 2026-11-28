#!/bin/bash
# shellcheck shell=bash
# Traefik ACME resolver discovery & verification — shared by start.sh and
# health-check.sh.
#
# Traefik registers its ACME resolvers on the command line
# (`--certificatesresolvers.<name>.acme.email=…` etc.). A router whose
# `tls.certresolver` label names a resolver that does not exist never obtains a
# certificate: Traefik logs "Router uses a nonexistent certificate resolver" and
# keeps serving its own default self-signed certificate, which a browser rejects
# and Cloudflare reports as error 526.
#
# Both functions are read-only — they inspect `docker ps`/`docker inspect` and
# never start, restart or reconfigure anything.

# Print the distinct ACME resolver names configured on the containers visible
# from this host, one per line. Empty output means "no Traefik found here", not
# a configuration error (the proxy may live on another host, or Traefik may be
# configured through a file instead of CLI flags).
detect_traefik_resolvers() {
	command -v docker >/dev/null 2>&1 || return 0

	docker ps --format '{{.Names}}' 2>/dev/null | while read -r container; do
		[ -n "$container" ] || continue
		docker inspect "$container" --format '{{json .Args}}' 2>/dev/null \
			| grep -oE -- '--certificatesresolvers\.[A-Za-z0-9_-]+\.' \
			| cut -d. -f2
	done | sort -u
}

# check_traefik_resolver [resolver-name]
#   exit 0 — the resolver exists, or no Traefik container is visible (the check
#            cannot decide; the caller verifies the served certificate instead)
#   exit 1 — a Traefik container is visible and the name is NOT among its
#            ACME resolvers, so no certificate will ever be issued
# Uses ${GREEN}/${YELLOW}/${RED}/${NC} when the caller defined them.
check_traefik_resolver() {
	local want="${1:-${TRAEFIK_CERTRESOLVER:-letsencrypt}}"
	local found

	found="$(detect_traefik_resolvers)"

	if [ -z "$found" ]; then
		echo -e "${YELLOW:-}⚠ No running Traefik container with --certificatesresolvers.* was found${NC:-}"
		echo -e "${YELLOW:-}  Cannot verify resolver '${want}' automatically (Traefik on another host? file-based static config?).${NC:-}"
		echo -e "${YELLOW:-}  The certificate Traefik actually serves is verified after start-up instead.${NC:-}"
		return 0
	fi

	if ! printf '%s\n' "$found" | grep -qx -- "$want"; then
		echo -e "${RED:-}❌ TRAEFIK_CERTRESOLVER='${want}' — the external Traefik has no such resolver${NC:-}"
		# shellcheck disable=SC2086 # newline-separated list, word splitting intended
		echo -e "${RED:-}   Available: $(printf '%s ' $found)${NC:-}"
		echo -e "${RED:-}   Without a matching name no certificate is issued (Cloudflare: error 526).${NC:-}"
		echo -e "${RED:-}   Set TRAEFIK_CERTRESOLVER in .env to one of the names above.${NC:-}"
		return 1
	fi

	echo -e "${GREEN:-}✓ ACME resolver '${want}' found on the external Traefik${NC:-}"

	return 0
}
