#!/usr/bin/env bash
# Builds an upload-ready ZIP for DirectAdmin shared hosting (no SSH, no Composer/Node on server).
#
# ZIP layout (extract inside /home/<user>/domains/faranepanel.ir/):
#   commission_app/          → code, vendor, storage  (NOT web-accessible)
#   public_html/commission/  → index.php, .htaccess, install.php, build assets
#
# Usage: scripts/build-release.sh            (from a clean working tree)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

VERSION="$(php -r '$c = require "config/app.php"; echo $c["version"];' 2>/dev/null || echo dev)"
SHA="$(git rev-parse --short HEAD)"
NAME="faran-commission-${VERSION}-${SHA}"
WORK="$ROOT/build/$NAME"
DIST="$ROOT/dist"

if [[ -n "$(git status --porcelain)" ]]; then
    echo "!! Working tree is not clean — the release is built from HEAD ($SHA), uncommitted changes are NOT included." >&2
fi

rm -rf "$WORK" && mkdir -p "$WORK/src" "$DIST"

echo "==> Frontend build"
npm ci --no-audit --no-fund >/dev/null
npm run build >/dev/null

echo "==> Export HEAD"
git archive HEAD | tar -x -C "$WORK/src"
cp -R public/build "$WORK/src/public/build"

echo "==> PHP dependencies (production only)"
cp -R vendor "$WORK/src/vendor"
(cd "$WORK/src" && composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-scripts --quiet)
(cd "$WORK/src" && php artisan package:discover --ansi >/dev/null)
find "$WORK/src/vendor" -type d -name .git -prune -exec rm -rf {} +
rm -rf "$WORK/src/vendor/bin"

echo "==> Assemble"
APP="$WORK/out/commission_app"
PUB="$WORK/out/public_html/commission"
mkdir -p "$APP" "$PUB"
cp -R "$WORK/src/public/." "$PUB/"
rm -rf "$WORK/src/public"
for p in node_modules tests resources/js resources/css package.json package-lock.json vite.config.js tsconfig.json phpunit.xml .editorconfig .gitattributes .npmrc scripts .github; do
    rm -rf "$WORK/src/$p"
done
cp -R "$WORK/src/." "$APP/"
rm -f "$APP/.env"
printf '%s\n' "<?php" "" "// Release layout: application code lives outside public_html." "return __DIR__.'/../../commission_app';" > "$PUB/paths.php"
printf '%s %s\n' "$VERSION" "$SHA" > "$APP/VERSION"

# storage skeleton (empty dirs must exist on the host)
for d in app/private framework/cache framework/sessions framework/views logs; do
    mkdir -p "$APP/storage/$d"
done
# Deny web access to the app folder even if it is ever moved under a web root.
printf '%s\n' "Require all denied" > "$APP/.htaccess"

echo "==> Zip"
rm -f "$DIST/$NAME.zip"
(cd "$WORK/out" && zip -qr -X "$DIST/$NAME.zip" commission_app public_html)
rm -rf "$WORK"

echo "Built $DIST/$NAME.zip ($(du -h "$DIST/$NAME.zip" | cut -f1))"
