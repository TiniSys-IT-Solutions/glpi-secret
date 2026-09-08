#!/usr/bin/env bash
set -euo pipefail

PLUGIN_KEY="secret"
REPOSITORY_NAME="glpi-secret"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAG_NAME="${1:-}"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}/build"
PACKAGE_DIR="${BUILD_DIR}/${PLUGIN_KEY}"

cd "${ROOT_DIR}"
PLUGIN_VERSION="$(sed -n "s/^const PLUGIN_SECRET_VERSION = '\([^']*\)';/\1/p" setup.php)"
[[ -n "${PLUGIN_VERSION}" ]] || { echo "Unable to read plugin version" >&2; exit 1; }
VERSION="${TAG_NAME#v}"
[[ -n "${VERSION}" ]] || VERSION="${PLUGIN_VERSION}"
[[ "${VERSION}" == "${PLUGIN_VERSION}" ]] || {
  echo "Version mismatch: ${VERSION} != ${PLUGIN_VERSION}" >&2
  exit 1
}
ARCHIVE="${DIST_DIR}/${REPOSITORY_NAME}-${VERSION}.zip"

for command in composer node php rg rsync python3 xgettext msginit msgmerge msgfmt msgattrib; do
  command -v "${command}" >/dev/null 2>&1 || { echo "Missing command: ${command}" >&2; exit 1; }
done

composer validate --strict --no-check-publish
php vendor/bin/phpunit
php vendor/bin/phpstan analyse
php vendor/bin/php-cs-fixer check --diff
node --check public/js/secret.js
php -r '$xml = simplexml_load_file("secret.xml"); exit($xml === false ? 1 : 0);'

rm -rf "${DIST_DIR}"

# Maintain the native GLPI gettext catalogs before packaging. English is the
# source language; the French catalog must remain fully translated.
vendor/bin/extract-locales
sed -i "s/Project-Id-Version: PACKAGE VERSION/Project-Id-Version: GLPI Secret ${VERSION}/" locales/en_GB.po
msgattrib --clear-fuzzy --output-file=locales/en_GB.po locales/en_GB.po
msgmerge --no-fuzzy-matching locales/fr_FR.po locales/secret.pot -o locales/fr_FR.po.new
mv locales/fr_FR.po.new locales/fr_FR.po
msgfmt --check --check-format --statistics -o locales/en_GB.mo locales/en_GB.po
msgfmt --check --check-format --statistics -o locales/fr_FR.mo locales/fr_FR.po
for locale in en_GB fr_FR; do
  if msgattrib --untranslated "locales/${locale}.po" | rg -q '^msgid '; then
    echo "${locale} catalog contains untranslated messages" >&2
    exit 1
  fi
done

mkdir -p "${PACKAGE_DIR}"
rsync -a ./ "${PACKAGE_DIR}/" \
  --exclude '.git/' --exclude '.github/' --exclude '.local/' \
  --exclude 'dist/' --exclude 'vendor/' --exclude 'tests/' \
  --exclude 'scripts/' --exclude '.gitignore' --exclude '.php-cs-fixer.php' \
  --exclude '.php-cs-fixer.cache' \
  --exclude 'phpstan.neon' --exclude 'phpunit.xml' --exclude 'AGENTS.md' \
  --exclude '.phpunit.cache/' --exclude '.phpunit.result.cache' --exclude '*~'

(
  cd "${PACKAGE_DIR}"
  composer install --no-dev --no-interaction --prefer-dist \
    --optimize-autoloader --classmap-authoritative
)
rm -f "${PACKAGE_DIR}/composer.lock"
find "${PACKAGE_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

ARCHIVE="${ARCHIVE}" BUILD_DIR="${BUILD_DIR}" PLUGIN_KEY="${PLUGIN_KEY}" python3 - <<'PY'
import os
import pathlib
import zipfile

archive = pathlib.Path(os.environ['ARCHIVE'])
build_dir = pathlib.Path(os.environ['BUILD_DIR'])
plugin_key = os.environ['PLUGIN_KEY']
plugin_dir = build_dir / plugin_key

with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as output:
    for path in plugin_dir.rglob('*'):
        if path.is_file():
            output.write(path, path.relative_to(build_dir))

with zipfile.ZipFile(archive) as package:
    names = set(package.namelist())
    required = {
        'secret/setup.php',
        'secret/hook.php',
        'secret/composer.json',
        'secret/front/config.php',
        'secret/logo.png',
        'secret/locales/en_GB.mo',
        'secret/locales/fr_FR.mo',
        'secret/locales/secret.pot',
        'secret/public/css/secret.css',
        'secret/public/js/secret.js',
        'secret/vendor/autoload.php',
    }
    missing = required - names
    if missing:
        raise SystemExit(f'Missing required entries: {sorted(missing)}')
    if any(not name.startswith('secret/') for name in names):
        raise SystemExit('Invalid archive root')
    forbidden = ('/.git/', '/.local/', '/tests/', '/dist/', '/scripts/')
    if any(any(part in name for part in forbidden) for name in names):
        raise SystemExit('Development files found in archive')

print(f'Verified {archive}: {len(names)} entries')
PY

echo "${ARCHIVE}"
