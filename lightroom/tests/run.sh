#!/bin/sh
# Test the Lightroom plug-in under Lua 5.1 (Lightroom's version) in Docker: unit tests,
# plus publishing to the local WordPress when it's running (npm start), with a temporary
# application password for admin.
#
#   sh lightroom/tests/run.sh                    # publishes the first JPEG in photos/web/
#   TEST_PHOTO=photo.jpg sh lightroom/tests/run.sh  # a JPEG with camera data
set -eu
cd "$(dirname "$0")/../.."

photo="${TEST_PHOTO:-$(ls photos/web/*.jpg 2>/dev/null | head -n 1 || true)}"
abs() { echo "$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"; }
cli="$(docker ps --format '{{.Names}}' | grep -E '^wp-env-.*-cli-1$' | grep -v -- '-tests-' | head -n 1 || true)"

set -- -v "$PWD/lightroom:/lightroom:ro"
if [ -n "$cli" ] && [ -n "$photo" ] && [ -f "$photo" ]; then
	network="$(docker inspect "$cli" --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{end}}')"
	password="$(docker exec "$cli" wp user application-password create admin lightroom-tests --porcelain)"
	revoke() {
		for uuid in $(docker exec "$cli" wp user application-password list admin --name=lightroom-tests --field=uuid); do
			docker exec "$cli" wp user application-password delete admin "$uuid" --quiet >/dev/null
		done
	}
	trap revoke EXIT
	set -- "$@" --network "$network" -v "$(abs "$photo"):/photo.jpg:ro" \
		-e WP_SITE=http://wordpress -e WP_USER=admin -e WP_PASSWORD="$password" -e TEST_PHOTO=/photo.jpg
else
	echo "Local WordPress or test photo not found: running the unit tests only."
fi

docker run --rm "$@" alpine:3.20 sh -c '
	apk add -q lua5.1 curl >/dev/null &&
	luac5.1 -p /lightroom/MiguelRojas.lrplugin/*.lua &&
	cd /tmp && LUA_PATH="/lightroom/tests/?.lua;/lightroom/MiguelRojas.lrplugin/?.lua" lua5.1 /lightroom/tests/test.lua'
