# Security policy

The detailed security model, trust boundaries, encryption requirements, and
backup constraints are documented in [docs/security.md](docs/security.md).

## Supported versions

Security fixes are provided for the latest published release and the current
`main` branch. Releases in the `0.0.x` series remain pre-production software.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability. Contact TiniSys IT
Solutions privately and include the affected version, impact, and reproduction
steps without sharing real credentials.

## Threat model

GLPI Secret is designed to protect against a database dump without the matching
`glpicrypt.key`, unauthorized GLPI users, accidental generic API/search export,
and disclosure through history or notifications.

It cannot protect a secret after full compromise of both the GLPI server and
its cryptographic key, from an administrator controlling the complete runtime,
or after an authorized user copies a revealed value elsewhere.

The ciphertext is explicitly excluded from generic API responses and GLPI
history. Plaintext is decrypted only after both the profile permission and the
secret ACL have passed. Reveal and copy are audited without recording values.
