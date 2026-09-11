# Roadmap

This roadmap follows the original project brief while reflecting what has been
implemented and field-tested. Version numbers remain in the `0.0.x` series
until deployment feedback confirms the permission model and operational fit.

## 0.0.21 — ITIL stable field-test candidate

- [x] Native Ticket, Change, and Problem timeline action.
- [x] Encrypted creation with GLPIKey and secured-field registration.
- [x] Owner, assigned-technician, requester-and-technician, and group ACLs.
- [x] GLPI profile rights, entity semantics, Central and Self-Service support.
- [x] Authorized timeline cards plus operator metadata tab.
- [x] Audited reveal, copy, update, delete, creation, and purge.
- [x] Safe notification followups containing no secret metadata or value.
- [x] Expiration policies and bounded automatic purge.
- [x] British English and French gettext catalogs.
- [x] Type-aware password, credential, token, and sensitive-information forms.
- [x] Automated unit, static-analysis, style, JavaScript, packaging, and release
  checks.

## Next — Asset integration (paused)

Work starts only after the 0.0.21 ITIL workflow has been observed in real use.

- [ ] Audit native asset classes and the Accounts plugin relation patterns.
- [ ] Add one polymorphic relation per asset without duplicating ciphertext.
- [ ] Add ACL-filtered asset tabs, counters, and creation controls.
- [ ] Define owner, explicit group, and entity-technician visibility without
  fragile class-specific rules.
- [ ] Define safe orphan handling before any asset-link deletion behavior.

## Candidate — Native service catalogue secret

- [x] Audit GLPI 11.0.8 form questions, answer persistence, destinations, and
  submission response.
- [ ] Prototype a native plugin question type that never serializes plaintext
  or ciphertext into the GLPI answer set.
- [ ] Prototype a plugin destination that links the encrypted secret to the
  Ticket, Change, or Problem created in the same form transaction.
- [ ] Define rollback, multiple-destination, anonymous-form, replay, orphan,
  expiration, and audit behavior before enabling the feature.
- [ ] Reject client-side ticket-link parsing, session storage, ordinary text
  questions, and custom cryptography as integration strategies.

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
