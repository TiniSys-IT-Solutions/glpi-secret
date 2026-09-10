# Changelog

All notable changes are documented here.

## 0.0.20 - 2026-09-10

### Security

- Close generic Secret, relation, and audit model access, including API lists,
  searches, writes, and purge. Dedicated ITIL controllers remain the only public
  access path and retain profile, relation, object, ACL, and audit checks.
- Respect native GLPI followup creation rights before publishing a notification.
- Enforce identical value limits on creation and replacement, validate ITIL
  visibility and group scope, and reject invalid dates and unsupported text.
- Keep closure expiration irreversible, preserve it before parent removal or
  reopening, and include it in bounded automatic maintenance.
- Preserve explicitly revoked profile rights during upgrades.
- Document key rotation requirements when encrypted tables are retained after
  plugin deactivation or uninstall.

### Fixed

- Verify the ITIL relation and actor context when reading the audit trail.
- Honour generator options for replacements and keep ambiguous characters inside
  their selected categories in both PHP and JavaScript.
- Retain existing timeline cards when new secret creation is disabled.
- Report notification failures after a successful save without inviting a retry.

### Changed

- Count authorized secrets in SQL and batch closure checks instead of performing
  them once per secret. Paginate the operator tab and the audit trail.
- Report native cron volumes and maintenance failures, retain failed records,
  and rotate bounded purge batches so failures cannot starve subsequent records.
- Add audited Copy to Self-Service cards and clear revealed fields on demand,
  after 60 seconds, or on page exit, without claiming clipboard erasure.
- Add behavioral regression coverage and a disposable GLPI integration runner.
- Build release packages from an explicit distribution allow-list.

## 0.0.19 - 2026-09-09

### Added

- Add a maintained roadmap that records the completed ITIL milestone and keeps
  asset integration explicitly paused for the next development phase.

### Changed

- Promote the Ticket, Change, and Problem workflow to the first stable
  field-test candidate while retaining the cautious `0.0.x` version line.
- Complete the British English and French interface review, including all
  JavaScript errors, confirmations, and clipboard notifications.
- Apply verified ITIL actor context consistently to metadata, update, delete,
  and audit controls.

### Security

- Reject ITIL actor-derived ACL flags unless GLPI also authorizes access to the
  linked Ticket, Change, or Problem.
- Fail closed when purge auditing or either database deletion fails, and when
  relation deletion fails during an explicit secret deletion.
- Enforce server-side length limits for names and usernames and refuse metadata
  repository queries for unsupported or non-viewable ITIL objects.

## 0.0.17 - 2026-09-09

### Fixed

- Mark every modern Secret ITIL controller with GLPI's authenticated firewall
  strategy so both Central and Self-Service profiles can reach the controller;
  profile, item, entity, ACL, CSRF, and audit checks remain enforced inside the
  request path.

## 0.0.16 - 2026-09-09

### Fixed

- Refresh the ITIL creation form with GLPI's standalone page CSRF token just
  before submission and provide explicit standalone fallback tokens for
  reveal, copy, and audit actions rendered in dynamic timeline content.

## 0.0.15 - 2026-09-09

### Fixed

- Treat GLPI's native ITIL `canViewItem()` result as the item-scope authority
  for catalogue requesters, while retaining direct entity checks outside a
  verified linked ITIL context.
- Hide the full Secret tab in Self-Service and keep the authorized purple card
  as the only plugin-owned secret display in that interface.

## 0.0.14 - 2026-09-09

### Fixed

- Let GLPI 11's controller listener perform the configuration POST CSRF check
  once, instead of checking the already-consumed token again in the legacy
  configuration file.
- Refresh the active session from the profile's stored Secret rights during
  plugin initialization, including when GLPI built a Helpdesk profile before
  loading the plugin.

## 0.0.13 - 2026-09-09

### Changed

- Apply the purple Secret visual identity to the creation panel.
- Enforce GLPI's active-entity scope on every metadata, reveal, copy, update,
  delete, and audit decision, and on creation in an ITIL object's entity.

### Fixed

- Register Secret permissions in GLPI's native Helpdesk-right allow-list so
  Self-Service sessions retain their configured metadata, create, and reveal
  rights instead of silently losing them during profile loading.

## 0.0.12 - 2026-09-09

### Changed

- Give authorized timeline Secret cards a distinct purple-tinted background
  and remove the redundant masked-dot placeholder before Reveal.
- Bootstrap metadata, create, and reveal rights for every Helpdesk-interface
  profile, including custom service-catalogue profiles, while retaining the
  per-ticket ACL requirement.

## 0.0.11 - 2026-09-09

### Added

- Add a server-authorized Secret card to the native ITIL timeline alongside
  the public notification followup, with Reveal and an operator-only Manage
  shortcut to the Secret tab.

### Fixed

- Remove the empty timeline-state gutter that offset the creation panel.
- Make the reveal renderer work from both the metadata table and timeline card.
- Apply the new standard-profile defaults once to retained upgrade databases,
  filling only rights that are still zero.

## 0.0.10 - 2026-09-09

### Changed

- Show both the GLPI login and numeric user ID in authorized audit results.
- Reuse the configured cryptographic password generator when replacing a
  secret value from the ITIL tab.

### Fixed

- Add a short-lived legacy clipboard fallback for HTTP-only GLPI instances
  where the secure-context Clipboard API is unavailable.

## 0.0.9 - 2026-09-09

### Fixed

- Enforce the canonical ITIL creation endpoint in the plugin JavaScript at
  render time and submit time, including when GLPI serves stale timeline HTML
  containing the former route alias.
- Add the key icon and prioritize Secret among external ITIL tabs.
- Report reveal, copy, and audit HTTP failures through a visible GLPI toast
  instead of silently discarding them.
- Use GLPI 11's page-level AJAX CSRF token and request marker for reveal, copy,
  and audit calls instead of a Twig-generated button token that produced 403
  responses from the controller listener.
- Bootstrap least-privilege Secret rights for GLPI's built-in Self-Service and
  ITIL operator profiles on first installation while leaving custom profiles
  disabled and preserving later administrator choices.

## 0.0.7 - 2026-09-09

### Added

- Add audited update, permanent delete, and audit consultation controls to the
  Ticket, Change, and Problem Secret tabs.
- Add a native GLPI followup after creation so normal recipients are notified
  with a type-specific ticket/change/problem sign-in instruction and no secret
  metadata or value.
- Add the configurable `purgeExpired` GLPI automatic action with bounded
  batches and retained audit events.

### Fixed

- Use canonical GLPI plugin URLs for create and reveal controllers so forms do
  not fall back to unresolved route names and return a 404 page.
- Ship the route correction under a distinct version so GLPI and deployment
  tooling cannot retain the earlier 0.0.6 template cache.
- Show the authorized secret creator and creation date in ITIL metadata tabs.

## 0.0.5 - 2026-09-08

### Changed

- Declare the JSON and Sodium PHP extension requirements through GLPI's native
  plugin requirement metadata.

### Fixed

- Normalize GLPI permission bitmasks to booleans to prevent strict return-type
  errors when loading the plugin configuration or general setup menus.
- Fail closed when the GLPI cryptographic key cannot be read or a ciphertext
  cannot be authenticated during reveal.
- Refuse to return revealed plaintext when its VIEW or COPY audit event cannot
  be persisted.

## 0.0.4 - 2026-09-08

### Added

- Native GLPI gettext catalogs for fully maintained British English and French
  interfaces.

### Changed

- Align plugin configuration, assets, and ITIL timeline hooks with
  the native GLPI 11 plugin integration points.
- Keep global settings in the native GLPI general configuration form; reserve
  a future Tools menu entry for secret centralization features.

### Fixed

- Restore the configuration wrench on the GLPI plugins page.
- Add the Secret tab in general setup and redirect the plugin wrench to it.
- Register public JavaScript and CSS assets with GLPI 11's public-directory
  path convention.
- Use GLPI-prefixed Symfony route names for secret creation and reveal actions.
- Match the native timeline answer-action contract with a stable action key,
  followup grouping, and a dedicated secure creation panel.
- Refresh the active profile's plugin rights after installation or upgrade so
  the Secret action is available without a logout/login cycle.

## 0.0.2 - 2026-09-08

### Added

- Native Secret action in Ticket, Change, and Problem timelines.
- Shared creation form with Web Crypto password generation.
- Owner, assigned-technician, requester-and-technician, and group visibility.
- ITIL tab with ACL-filtered metadata and reveal/copy actions.
- Dedicated POST controllers with GLPI 11 session and CSRF enforcement.
- Never, ITIL closure, 1/7/30-day, and custom expiration policies.
- Global configuration for ticket integration, defaults, limits, and generator.
- No-store reveal responses and VIEW/COPY audit events.

### Changed

- Align badges, licensing, project identity, release documentation, and
  contribution guidance with TiniSys IT Solutions plugin standards.

## 0.0.1 - 2026-09-08

### Added

- GLPI 11.0.8 plugin bootstrap and installable database schema.
- Native `GLPIKey` encryption with key-rotation field registration.
- Secret, polymorphic item relation, and immutable audit models.
- Central profile-right and per-secret ACL services.
- API field suppression and history exclusion for ciphertext.
- Cryptographically secure password generator.
- Unit tests and automated quality/release workflows.
