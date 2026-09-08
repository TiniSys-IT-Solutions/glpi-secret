# GLPI Secret engineering invariants

- Target the exact supported GLPI 11 range declared in `setup.php`.
- Never store plaintext secrets in the database, logs, history, notifications,
  HTML, generic APIs, or preloaded JavaScript.
- Never expose `encrypted_value` through search options or generic GLPI APIs.
- Never bypass `SecretAccessService` for authorization.
- Profile permission and per-secret ACL must both pass.
- Never use the GLPI REST API from code running inside GLPI.
- Never implement custom cryptography while `GLPIKey` satisfies the requirement.
- Fail closed if `GLPIKey` cannot produce a ciphertext.
- Never duplicate a secret when linking it to multiple GLPI items.
- All reveal and copy operations must be server-authorized and audited.
- Audit context is allow-listed and must never contain secret material.
- Do not silently delete encrypted records during plugin uninstall.
- Update architecture, security, permissions, and user documentation whenever
  behavior changes materially.
- A release tag must be `vX.Y.Z` and match `PLUGIN_SECRET_VERSION` exactly.

