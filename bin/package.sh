#!/bin/sh
# Build installable zips of the theme and plugin, for uploading in wp-admin
# (Appearance > Themes > Add New > Upload, Plugins > Add New > Upload) or over SFTP.
set -e
cd "$(dirname "$0")/.."

rm -rf dist
mkdir dist
(cd themes && zip -qr ../dist/photos-theme.zip photos -x '*.DS_Store')
(cd plugins && zip -qr ../dist/photos-core.zip photos-core -x '*.DS_Store')
ls -lh dist
