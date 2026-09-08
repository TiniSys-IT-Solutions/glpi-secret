<p align="center">
  <img src="public/logo_glpi_secret.png" alt="GLPI Secret — TiniSys IT Solutions" width="420">
</p>

<h1 align="center">GLPI Secret</h1>

<p align="center">
  <a href="https://github.com/TiniSys-IT-Solutions/glpi-secret/releases"><img src="https://img.shields.io/github/v/release/TiniSys-IT-Solutions/glpi-secret?display_name=tag&sort=semver&style=for-the-badge" alt="Latest release"></a>
  <a href="https://github.com/TiniSys-IT-Solutions/glpi-secret/actions/workflows/ci.yml"><img src="https://img.shields.io/github/actions/workflow/status/TiniSys-IT-Solutions/glpi-secret/ci.yml?branch=main&style=for-the-badge&label=CI" alt="CI status"></a>
  <img src="https://img.shields.io/badge/GLPI-11.0.8%2B-0B5CAD?style=for-the-badge" alt="GLPI 11.0.8 or newer">
  <a href="LICENSE"><img src="https://img.shields.io/github/license/TiniSys-IT-Solutions/glpi-secret?style=for-the-badge" alt="GPL-3.0-or-later"></a>
</p>

GLPI Secret provides native storage for operational credentials attached to
GLPI workflows and assets. Its purpose is to prevent passwords and tokens from
being pasted into unprotected ticket text or asset notes.

Version 0.0.2 includes the Ticket, Change, and Problem workflow. Asset
integration is intentionally deferred to the next milestone.

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

## Requirements

- GLPI 11.0.8 through 11.0.x;
- PHP 8.2 or newer with Sodium;
- a readable GLPI `glpicrypt.key`.

## Installation

Install the release archive so that `setup.php` is located at
`plugins/secret/setup.php`, then install and enable **Secret** from GLPI's plugin
management page.

For development:

```bash
composer install
composer quality
./scripts/build-release.sh
```

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

## License

GPL-3.0-or-later. Copyright TiniSys IT Solutions.
