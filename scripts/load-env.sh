#!/bin/sh
# DockerCart - safe .env loader
# Sourced by start.sh / update.sh / health-check.sh so a stray non-KEY=value
# line in .env (e.g. a bare filename left by a bad persist write) can never be
# executed as a shell command. Only lines matching ^[A-Za-z_][A-Za-z0-9_]*= are
# exported; comments, blanks and bare tokens are skipped. Works under bash and dash.

load_env() {
	# Allow the function to be sourced even under `set -e`: an absent file is
	# not an error — callers already guard with [ -f .env ].
	[ -f "$1" ] || return 0
	set -a
	while IFS= read -r line || [ -n "$line" ]; do
		case "$line" in
			# Skip blanks, comments, and any line that is not KEY=value.
			''|\#*|*[!A-Za-z0-9_=]*=*) : ;;
			[_A-Za-z][_A-Za-z0-9]*=*) export "$line" ;;
		esac
	done < "$1"
	set +a
}
