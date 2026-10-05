<p align="center">
  <img src="public/logo_glpi_secret.png" alt="GLPI Secret — TiniSys IT Solutions" width="420">
</p>

<h1 align="center">GLPI Secret</h1>

<p align="center">
  <img src="https://img.shields.io/badge/version-0.2.6-blue" alt="Version 0.2.6">
  <img src="https://img.shields.io/badge/GLPI-%3E%3D11.0.8_%3C11.1.0-blue" alt="GLPI 11.0.8 to 11.0.x">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777bb4" alt="PHP 8.2 or newer">
  <img src="https://img.shields.io/badge/license-GPL--3.0--or--later-green" alt="License GPL-3.0-or-later">
  <img src="https://img.shields.io/badge/status-production-success" alt="Status: production">
</p>

GLPI Secret provides native storage for operational credentials attached to
GLPI ITIL workflows. Its purpose is to prevent passwords and tokens from being
pasted into unprotected Ticket, Change or Problem text.

Version 0.2.6 extends the production Ticket, Change, and Problem
workflow with asset tabs, shared links, entity-aware categories and a central
metadata view.

## Features

- Native Secret creation and authorized cards in Ticket, Change and Problem timelines.
- Secret tabs on native and custom GLPI assets, with shared links to one encrypted value.
- Password, username/password, token and multiline sensitive-information types.
- Browser password generation, visibility rules, expiration and automatic maintenance.
- Native **Tools > Secrets** search, item tabs, audited linking and permanent-delete massive actions.
- Entity-aware category trees managed through native GLPI dropdown setup.
- British English and French interfaces through GLPI's gettext catalogs.

## Security

- encryption through GLPI's `GLPIKey` and `glpicrypt.key`;
- integration with GLPI key rotation through secured fields;
- profile rights combined with per-secret ACL rules;
- GLPI-native active-entity and recursive-profile scoping on every action;
- generic APIs closed; native central search exposes authorized metadata only;
- reveal and copy operations routed through one audited service;
- no external service and no call to the GLPI REST API from inside GLPI;
- native Secret timeline action and ACL-filtered ITIL tab;
- dedicated reveal/copy requests with no-store responses and audit events;
- one ciphertext may be linked to several native or custom GLPI assets;
- asset ACLs combine native asset access with action-specific Secret rights;
- configurable visibility, expiration, and password generation defaults.

## Requirements

- GLPI `>= 11.0.8` and `< 11.1.0`;
- PHP `>= 8.2` with Sodium;
- a readable GLPI `glpicrypt.key`.

The plugin interface is maintained in British English and French through
GLPI's native gettext catalogs.

## Installation

Download `secret-0.2.6.zip` from [Releases](https://github.com/TiniSys-IT-Solutions/glpi-secret/releases).
Back up the database and matching `glpicrypt.key`, then extract the archive so
that `setup.php` is located at `plugins/secret/setup.php`. Install and enable
**Secret** from GLPI's plugin management page. Marketplace installations use
the same `secret/` directory and canonical plugin URLs.

For an existing installation, replace the plugin files and run GLPI's standard
plugin update action. See [Installation](docs/installation.md) and
[Upgrade](docs/upgrade.md). Uninstall retains encrypted data and audit rows.

## Configuration

Configure rights under **Administration > Profiles > Secret**. Open the plugin
wrench or the **Secret** tab in **Setup > General** to configure ITIL and asset
creation, default visibility, expiration, generator options and value limits.
The administrator ACL override is disabled by default.

Secret administrators manage category trees under **Setup > Dropdowns > Secret**.
Categories are scoped to entities and may be recursive.

## Usage

Create a secret from the native ITIL **Reply > Secret** action or an asset's
**Secrets** tab. Choose its visibility and expiration before saving. Reveal and
Copy perform a fresh authorization check and record an audit event.

Use **Tools > Secrets** to search authorized metadata, open a secret's form,
replace its value, inspect history or link it to another asset. Permanent deletion
breaks all links and requires confirmation. Self-Service users use authorized
timeline cards; the full Secret tab is reserved for the Central interface.

## Permissions

Metadata, create, reveal, update, delete, audit and administration rights are
independent. Every operation also checks native access to the linked GLPI object
and the secret's owner, actor or group visibility. Administration alone does not
authorize another user's private secret.

For massive actions, asset linking requires update rights; permanent deletion
requires delete rights independently. See [Permissions](docs/permissions.md).

## Compatibility

The supported range is GLPI `>= 11.0.8` and `< 11.1.0`, with PHP `>= 8.2`.
Manual and Marketplace installations use GLPI's native plugin routes and assets.
No external service is required. Automated PHP checks and source inspection do
not replace installation and browser validation on a test GLPI instance.

## Development

GNU gettext (`xgettext`, `msginit`, `msgmerge`, `msgfmt`, and `msgattrib`) is
required to maintain and validate the native GLPI language catalogs.

```bash
composer install
composer quality
node --test tests/JavaScript/*.cjs
```

Run integration tests only against a disposable GLPI instance and never use
production credentials in tests, fixtures, issues, or pull requests. See
[CONTRIBUTING.md](CONTRIBUTING.md) for the expected checks and security
invariants.

## Build

```bash
./scripts/build-release.sh
```

The build validates code and translations and creates `dist/secret/` plus
`dist/secret-0.2.6.zip`, rooted at `secret/`. It uses an explicit distribution
allow-list and excludes local files, tests and development dependencies.
Composer, Node.js, PHP, ripgrep, rsync, Python 3 and GNU gettext are build tools;
they are not additional services required by the installed plugin.

For the same sources and tool versions, `SOURCE_DATE_EPOCH` controls generated
catalog and ZIP timestamps. It defaults to the latest commit timestamp, or
1980-01-01 for a source tree without Git metadata. Set it explicitly when
comparing builds from different checkouts. Tagged releases publish the matching
archive as a GitHub Release asset; generated packages are never committed.

## Backup

A usable restoration requires both the GLPI database and the matching
`glpicrypt.key`. Never store that key in this repository, the database, plugin
configuration, or application logs.

## Documentation

- [Architecture](docs/architecture.md)
- [Security model](docs/security.md)
- [Permissions](docs/permissions.md)
- [Ticket and ITIL workflow](docs/ticket-workflow.md)
- [Encryption and backup](docs/encryption.md)
- [Installation](docs/installation.md)
- [Upgrade](docs/upgrade.md)
- [Roadmap](ROADMAP.md)

## Project identity

This plugin is independently developed and maintained by TiniSys IT Solutions.
GLPI is a trademark of its respective owners. This project is an independent
integration and is not an official GLPI product.

## Licence

GPL-3.0-or-later. See [LICENSE](LICENSE).
