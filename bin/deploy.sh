#!/bin/bash
# Deploy the theme and plugin to the live WordPress.com site over SSH.
#
#   npm run deploy                     # deploys what's committed in themes/ and plugins/
#   npm run deploy -- --allow-dirty    # also deploys uncommitted changes
#
# Settings come from .deploy.env (not committed); see .deploy.env.example.
set -euo pipefail
cd "$(dirname "$0")/.."

WPCOM_SSH_HOST="miguelrojasphoto.wordpress.com@ssh.wp.com"
WPCOM_SSH_KEY=""
if [ -f .deploy.env ]; then
	# shellcheck disable=SC1091
	. ./.deploy.env
fi

dirty="$(git status --porcelain -- themes plugins)"
if [ -n "$dirty" ] && [ "${1:-}" != "--allow-dirty" ]; then
	echo "Uncommitted changes in themes/ or plugins/:" >&2
	echo "$dirty" >&2
	echo "Commit them first, or run: npm run deploy -- --allow-dirty" >&2
	exit 1
fi

ssh_opts=(-o BatchMode=yes -o ConnectTimeout=20)
if [ -n "$WPCOM_SSH_KEY" ]; then
	# Use this key (from the default agent if loaded there), ignoring other identities
	# ~/.ssh/config may set for ssh.wp.com.
	ssh_opts+=(-o IdentityAgent="${SSH_AUTH_SOCK:-none}" -o IdentityFile="$WPCOM_SSH_KEY" -o IdentitiesOnly=yes)
fi

sh bin/package.sh > /dev/null
echo "Deploying $(git rev-parse --short HEAD)${dirty:+ plus uncommitted changes} to $WPCOM_SSH_HOST"

# Stream the zips into a private temp dir on the server. (scp/SFTP would drop them in the
# public web root.) Installing with --force replaces the old version in place.
COPYFILE_DISABLE=1 tar --no-xattrs -C dist -cf - photos-theme.zip photos-core.zip | ssh "${ssh_opts[@]}" "$WPCOM_SSH_HOST" '
	set -e
	dir=$(mktemp -d)
	trap "rm -rf \"$dir\"" EXIT
	tar -C "$dir" -xf -
	wp theme install "$dir/photos-theme.zip" --force
	wp plugin install "$dir/photos-core.zip" --force
	wp plugin is-active photos-core || wp plugin activate photos-core
	wp cache flush
	# WordPress.com also caches whole pages for visitors; clear them so changes show at once.
	wp edge-cache purge --domain --yes || echo "Edge cache not purged; pages may update a few minutes late."
	wp theme list --name=photos --fields=name,status,version
	wp plugin list --name=photos-core --fields=name,status,version
'
echo "Done."
