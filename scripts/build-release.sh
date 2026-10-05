#!/usr/bin/env bash
set -euo pipefail

PLUGIN_KEY="secret"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAG_NAME="${1:-}"
DIST_DIR="${ROOT_DIR}/dist"
PACKAGE_DIR="${DIST_DIR}/${PLUGIN_KEY}"

cd "${ROOT_DIR}"
PLUGIN_VERSION="$(sed -n "s/^const PLUGIN_SECRET_VERSION = '\([^']*\)';/\1/p" setup.php)"
[[ -n "${PLUGIN_VERSION}" ]] || { echo "Unable to read plugin version" >&2; exit 1; }
if [[ -n "${TAG_NAME}" && ! "${TAG_NAME}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Expected a vX.Y.Z release tag" >&2
  exit 1
fi
VERSION="${TAG_NAME#v}"
[[ -n "${VERSION}" ]] || VERSION="${PLUGIN_VERSION}"
[[ "${VERSION}" == "${PLUGIN_VERSION}" ]] || {
  echo "Version mismatch: ${VERSION} != ${PLUGIN_VERSION}" >&2
  exit 1
}
ARCHIVE="${DIST_DIR}/${PLUGIN_KEY}-${VERSION}.zip"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-$(git log -1 --format=%ct 2>/dev/null || printf '315532800')}"
[[ "${SOURCE_DATE_EPOCH}" =~ ^[0-9]+$ ]] || { echo "Invalid SOURCE_DATE_EPOCH" >&2; exit 1; }
export SOURCE_DATE_EPOCH

for command in composer node php rg rsync python3 xgettext msginit msgmerge msgfmt msgattrib; do
  command -v "${command}" >/dev/null 2>&1 || { echo "Missing command: ${command}" >&2; exit 1; }
done

composer validate --strict --no-check-publish
php vendor/bin/phpunit
php vendor/bin/phpstan analyse
php vendor/bin/php-cs-fixer check --diff
node --check public/js/secret.js
node --test tests/JavaScript/*.cjs
VERSION="${VERSION}" php -r '
  $xml = simplexml_load_file("secret.xml");
  if ($xml === false) { exit(1); }
  $release = $xml->versions->version[0];
  $version = getenv("VERSION");
  exit((string) $release->num === $version
    && str_ends_with((string) $release->download_url, "/secret-" . $version . ".zip") ? 0 : 1);
'

# Always regenerate translations: stale .mo files cannot validate a release.
# The native extractor does not exclude dist/ from its PHP/JS scan.
rm -rf "${DIST_DIR}"
vendor/bin/extract-locales
sed -i "s/Project-Id-Version: PACKAGE VERSION/Project-Id-Version: GLPI Secret ${VERSION}/" locales/secret.pot locales/en_GB.po
msgattrib --clear-fuzzy --output-file=locales/en_GB.po locales/en_GB.po
msgmerge --no-fuzzy-matching locales/fr_FR.po locales/secret.pot -o locales/fr_FR.po.new
mv locales/fr_FR.po.new locales/fr_FR.po
# Normalize the generated headers as well as ZIP timestamps for repeatable builds.
VERSION="${VERSION}" python3 - <<'PY'
import datetime
import os
import pathlib
import re

stamp = datetime.datetime.fromtimestamp(int(os.environ['SOURCE_DATE_EPOCH']), datetime.timezone.utc)
for path in [pathlib.Path('locales/secret.pot'), *pathlib.Path('locales').glob('*.po')]:
    content = path.read_text()
    content = re.sub(r'Project-Id-Version: [^\\\n]*', 'Project-Id-Version: GLPI Secret ' + os.environ['VERSION'], content)
    content = re.sub(r'POT-Creation-Date: [^\\\n]*', 'POT-Creation-Date: ' + stamp.strftime('%Y-%m-%d %H:%M%z'), content)
    if path.name == 'en_GB.po':
        content = re.sub(r'PO-Revision-Date: [^\\\n]*', 'PO-Revision-Date: ' + stamp.strftime('%Y-%m-%d %H:%M%z'), content)
        content = ('# British English translations for GLPI Secret.\n'
                   f'# Copyright (C) {stamp.year} TiniSys IT Solutions\n'
                   '# Distributed under the same licence as GLPI Secret.\n#\n'
                   + content[content.index('msgid ""'):])
    path.write_text(content)
PY
for locale in en_GB fr_FR; do
  msgfmt --check --check-format --statistics -o "locales/${locale}.mo" "locales/${locale}.po"
  if [[ -n "$(msgattrib --untranslated --no-obsolete "locales/${locale}.po" | rg '^msgid ' || true)" ]]; then
    echo "${locale} catalog contains untranslated messages" >&2
    exit 1
  fi
done

mkdir -p "${PACKAGE_DIR}"
for entry in setup.php hook.php composer.json composer.lock secret.xml LICENSE README.md CHANGELOG.md SECURITY.md CONTRIBUTING.md ROADMAP.md logo.png src front templates public locales docs; do
  rsync -a --exclude '.*' --exclude '*~' --exclude '*.bak' "${entry}" "${PACKAGE_DIR}/"
done

(
  cd "${PACKAGE_DIR}"
  composer install --no-dev --no-interaction --prefer-dist \
    --optimize-autoloader --classmap-authoritative
)
rm -f "${PACKAGE_DIR}/composer.lock"
find "${PACKAGE_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

ARCHIVE="${ARCHIVE}" DIST_DIR="${DIST_DIR}" PLUGIN_KEY="${PLUGIN_KEY}" VERSION="${VERSION}" python3 - <<'PY'
import datetime
import os
import pathlib
import zipfile

archive = pathlib.Path(os.environ['ARCHIVE'])
dist_dir = pathlib.Path(os.environ['DIST_DIR'])
plugin_key = os.environ['PLUGIN_KEY']
plugin_dir = dist_dir / plugin_key
stamp = datetime.datetime.fromtimestamp(max(315532800, int(os.environ['SOURCE_DATE_EPOCH'])), datetime.timezone.utc)

with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as output:
    for path in sorted(plugin_dir.rglob('*')):
        if path.is_file():
            entry = zipfile.ZipInfo(path.relative_to(dist_dir).as_posix(), stamp.timetuple()[:6])
            entry.compress_type = zipfile.ZIP_DEFLATED
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            output.writestr(entry, path.read_bytes())

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
    required.update(path.relative_to(dist_dir).as_posix() for folder in ('src', 'front', 'templates', 'public', 'locales')
                    for path in (plugin_dir / folder).rglob('*') if path.is_file())
    missing = required - names
    if missing:
        raise SystemExit(f'Missing required entries: {sorted(missing)}')
    if any(not name.startswith('secret/') for name in names):
        raise SystemExit('Invalid archive root')
    forbidden = ('/.git/', '/.local/', '/.agents/', '/.codex/', '/tests/', '/dist/', '/scripts/')
    if any(any(part in name for part in forbidden)
           or any(part.startswith('.') for part in pathlib.PurePosixPath(name).parts)
           or name.endswith(('~', '.bak', '.log', '.sql', '.orig', '.rej'))
           or pathlib.PurePosixPath(name).name == 'auth.json' for name in names):
        raise SystemExit('Development files found in archive')
    setup = package.read('secret/setup.php').decode()
    if f"const PLUGIN_SECRET_VERSION = '{os.environ['VERSION']}';" not in setup:
        raise SystemExit('Packaged version mismatch')
    if package.testzip() is not None:
        raise SystemExit('Corrupt ZIP entry')

print(f'Verified {archive}: {len(names)} entries')
PY

echo "${ARCHIVE}"
