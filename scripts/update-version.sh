#!/bin/sh

set -eu

version=${1:-}
if [ "$#" -ne 1 ] || ! printf '%s\n' "$version" | grep -Eq '^[0-9]+[.][0-9]+[.][0-9]+$'; then
    echo "Usage: $0 MAJEUR.MINEUR.CORRECTIF" >&2
    exit 2
fi

printf '%s\n' "$version" >version.txt
sed -i.bak "s/public const string VERSION = '.*'; \/\/ x-release-version/public const string VERSION = '$version'; \/\/ x-release-version/" app/src/VersionApplication.php
rm -f app/src/VersionApplication.php.bak
grep -Fqx "    public const string VERSION = '$version'; // x-release-version" app/src/VersionApplication.php
