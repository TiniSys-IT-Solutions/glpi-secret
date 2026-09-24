# Roadmap

This roadmap follows the original project brief while reflecting what has been
implemented and field-tested. Version 0.1.0 marks the first production ITIL
scope after deployment validation.

## 0.1.0 — ITIL production release

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

## 0.2.0 — Asset integration

- [x] Discover every native and custom type in `$CFG_GLPI['asset_types']`.
- [x] Reuse the polymorphic relation without duplicating ciphertext.
- [x] Add ACL-filtered asset tabs, counters, creation and existing-secret links.
- [x] Define owner, explicit group and configured technical-profile visibility.
- [x] Audit link changes and purge ciphertext only after the final link is gone.
- [x] Add entity-aware recursive Secret categories.
- [x] Add an ACL-filtered metadata-only **Tools > Secrets** page.

## 0.2.2 — GLPI-native interface follow-up

The following items come from the first GLPI 11.0.8 test of the 0.2.1 package
and are implemented in 0.2.2.

### Secret settings layout

- [x] Move **Default visibility** into **Ticket and ITIL integration**. Asset
  access must not inherit requester or Self-Service visibility semantics.
- [x] Reorganize **Maximum secret length** and the expiration defaults alongside
  the password-generator settings, while checking whether expiration needs
  separate ITIL and asset defaults rather than one misleading shared value.
- [x] Remove **Technical profiles allowed by asset ACLs** from the settings.
  Asset authorization now combines native asset access, the per-secret ACL and
  the action-specific Secret right without another profile selector.

### Native central Secret list

- [x] Restore GLPI's native row-selection checkboxes and **Actions** control on
  `/plugins/secret/front/secret.php`.
- [x] Review only meaningful native bulk actions. Provide the documented
  **Link to an asset** specific action; do not expose reveal/copy, trash or
  transfer because their multi-relation semantics are unsafe or undefined.

### Native Secret form

- [x] Diagnose why `/plugins/secret/front/secret.form.php?id=<id>` displays its
  four left-hand tabs but loads no content or controls. Verify both the initial
  form and GLPI's `common.tabs.php` AJAX requests against object ACLs.
- [x] Populate the main **Secret**, **Linked items**, and **History** tabs with
  authorized metadata and actions. Keep the security log labelled **History**
  in the UI without enabling generic GLPI history for encrypted fields.

### Asset and ITIL object tabs

- [x] Reduce each linked-object row to the relevant **Reveal** action and make
  the Secret name link to `/plugins/secret/front/secret.form.php?id=<id>`.
- [x] Keep update, history and destructive operations on the dedicated Secret
  form instead of duplicating them on every linked-object row. Linking remains
  an explicit asset-tab or GLPI massive action.
- [x] Add an eye control beside **Generate** to show or hide only the unsaved
   locally generated value. It must not call a reveal endpoint, preload stored
   plaintext, or write the value to logs, history, HTML attributes or storage.

## 0.2.3 — Central Secret management follow-up

- [x] Show the linked GLPI item type as a sortable and filterable native search
  column, with every available Secret column displayed by default.
- [x] Add a confirmed, audited permanent-delete massive action that explicitly
  warns that every relation will be removed.
- [x] Keep the edit form visible and allow replacement of the encrypted value
  without ever preloading plaintext.
- [x] Link a Secret to another authorized asset from the Linked items tab using
  GLPI's native dependent item-type and item selector.

## 0.2.4 — Display-preference follow-up

- [x] Replace forced search columns with GLPI's native global display
  preferences so personal column choices remain authoritative.
- [x] Collapse the highlighted editor by default and expose password generation
  beside the replacement value.

## 0.2.5 — Release audit

- [x] Audit the complete source tree, migration path, security boundaries,
  documentation, translation catalogs and release archive.
- [x] Remove obsolete code and translation references.
- [x] Exclude local tooling and generated artifacts from the public repository.
- [x] Validate dependency advisories and the PHP 8.2/8.4 quality matrix.

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

## Later — Maintenance

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
