# Roadmap

This roadmap follows the original project brief while reflecting what has been
implemented and field-tested. Version numbers remain in the `0.0.x` series
until deployment feedback confirms the permission model and operational fit.

## 0.0.20 — ITIL stable field-test candidate

- [x] Native Ticket, Change, and Problem timeline action.
- [x] Encrypted creation with GLPIKey and secured-field registration.
- [x] Owner, assigned-technician, requester-and-technician, and group ACLs.
- [x] GLPI profile rights, entity semantics, Central and Self-Service support.
- [x] Authorized timeline cards plus operator metadata tab.
- [x] Audited reveal, copy, update, delete, creation, and purge.
- [x] Safe notification followups containing no secret metadata or value.
- [x] Expiration policies and bounded automatic purge.
- [x] British English and French gettext catalogs.
- [x] Automated unit, static-analysis, style, JavaScript, packaging, and release
  checks.

## Next — Asset integration (paused)

Work starts only after the 0.0.20 ITIL workflow has been observed in real use.

- [ ] Audit native asset classes and the Accounts plugin relation patterns.
- [ ] Add one polymorphic relation per asset without duplicating ciphertext.
- [ ] Add ACL-filtered asset tabs, counters, and creation controls.
- [ ] Define owner, explicit group, and entity-technician visibility without
  fragile class-specific rules.
- [ ] Define safe orphan handling before any asset-link deletion behavior.

## Later — Central management and maintenance

- [ ] Add a metadata-only central Secrets page with safe filters.
- [ ] Add orphan detection and explicit maintenance workflows.
- [ ] Evaluate encrypted export and recovery tooling without copying
  `glpicrypt.key` or exposing plaintext.
- [ ] Expand supported secret types only when a validated operational need
  exists.

## Release gates

Every release must keep the tag and `PLUGIN_SECRET_VERSION` identical, update
the changelog and relevant security/permission/user documentation, preserve
existing encrypted data during upgrades and uninstall, and pass the complete
quality and package checks.
