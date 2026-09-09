<p align="center">
  <img src="public/logo_glpi_secret.png" alt="GLPI Secret — TiniSys IT Solutions" width="420">
</p>

<h1 align="center">GLPI Secret</h1>

<p align="center">
  <img src="https://img.shields.io/badge/GLPI-11.x-blue" alt="GLPI 11">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777bb4" alt="PHP 8.2 or newer">
  <img src="https://img.shields.io/badge/license-GPL--3.0--or--later-green" alt="License GPL-3.0-or-later">
  <img src="https://img.shields.io/badge/status-early%20development-orange" alt="Status: early development">
</p>

GLPI Secret provides native storage for operational credentials attached to
GLPI workflows and assets. Its purpose is to prevent passwords and tokens from
being pasted into unprotected ticket text or asset notes.

Version 0.0.7 includes the Ticket, Change, and Problem workflow. Asset
integration is intentionally deferred to the next milestone.

> Status: early development (`0.0.x`). Validate the plugin and its permission
> model in a disposable GLPI environment before using it in production.

## Security foundations

- encryption through GLPI's `GLPIKey` and `glpicrypt.key`;
- integration with GLPI key rotation through secured fields;
- profile rights combined with per-secret ACL rules;
- ciphertext hidden from generic GLPI API responses and searches;
- reveal and copy operations routed through one audited service;
- no external service and no call to the GLPI REST API from inside GLPI.
- native Secret timeline action and ACL-filtered ITIL tab;
- dedicated reveal/copy requests with no-store responses and audit events;
- configurable visibility, expiration, and password generation defaults.

## Compatibility

- GLPI `>= 11.0.8` and `< 11.1.0`;
- PHP `>= 8.2` with Sodium;
- a readable GLPI `glpicrypt.key`.

The plugin interface is maintained in British English and French through
GLPI's native gettext catalogs.

## Installation

Install the release archive so that `setup.php` is located at
`plugins/secret/setup.php`, then install and enable **Secret** from GLPI's plugin
management page.

## Development and tests

GNU gettext (`xgettext`, `msginit`, `msgmerge`, `msgfmt`, and `msgattrib`) is
required to maintain and validate the native GLPI language catalogs.

```bash
composer install
composer quality
./scripts/build-release.sh
```

Run integration tests only against a disposable GLPI instance and never use
production credentials in tests, fixtures, issues, or pull requests. See
[CONTRIBUTING.md](CONTRIBUTING.md) for the expected checks and security
invariants.

Installable ZIP archives are generated in the ignored local `dist/` directory.
Tagged releases publish the matching archive as a GitHub Release asset;
generated packages are never committed to the source repository.

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

## Project identity

This plugin is independently developed and maintained by TiniSys IT Solutions.
GLPI is a trademark of its respective owners. This project is an independent
integration and is not an official GLPI product.

## Licence

GPL-3.0-or-later. See [LICENSE](LICENSE).
