#!/bin/sh
# DockerCart - project name sanitizer
# Derive a valid Docker/podman Compose project name from an arbitrary string
# (typically a directory basename). The result matches
# ^[a-zA-Z0-9][a-zA-Z0-9_.-]*$ which is safe for container, network, volume and
# pod names under both docker compose and podman-compose.
#
# Transformation: lowercase; every character outside [a-z0-9] becomes '-';
# repeated dashes collapse; leading/trailing dashes and leading digits are
# stripped. If nothing valid remains, fall back to "dockercart".

sanitize_project_name() {
	[ $# -ge 1 ] || { printf 'dockercart'; return 0; }
	raw="$1"
	out=$(printf '%s' "$raw" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9' '-' | tr -s '-' | sed -e 's/^-*//' -e 's/-*$//' -e 's/^[0-9]*//')
	[ -z "$out" ] && out='dockercart'
	printf '%s' "$out"
}
