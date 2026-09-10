# Security model

All public secret operations use the dedicated authenticated ITIL controllers.
Authorization combines the requested Secret profile right, a verified relation
with a viewable Ticket/Change/Problem, and the per-secret ACL. GLPI's native
`canViewItem()` is authoritative for that linked object's scope, including
legitimate catalogue requesters outside their directly active entity set.
Direct service access without verified ITIL context requires active-entity scope.

Version 0.0.20 deliberately denies generic access to Secret, SecretItem and
SecretLog: native canView/canCreate/canUpdate/canDelete/canPurge are false, generic
search options are empty, and system list criteria match no records. The model
classes remain available internally for GLPIKey rotation and audited application
services. A profile administrator cannot bypass an individual secret ACL by
using the generic API, search, dropdown or purge action. There is no generic
Secret API integration in this ITIL milestone.

`encrypted_value` remains undisclosed and excluded from GLPI history. No initial
page contains plaintext. Reveal/Copy use POST with native session and CSRF
protection and no-store responses; a value is returned only after a successful
VIEW/COPY audit. Audit input is allow-listed; values and cryptographic material
must never enter it. Model form retry buffers do not retain secret input.

Secret values must be nonempty UTF-8 text without NUL bytes and within the
configured byte limit on both creation and replacement. Metadata limits use
Unicode character counts. Only ITIL visibility choices are accepted. An explicit
group must exist, be assignable and belong to the item's entity or a recursive
ancestor. Entity-technician visibility remains unavailable in this phase.

A native notification followup is added only if GLPI allows the current user to
create it. Otherwise the encrypted secret remains saved, with a generic warning.
No notification includes a value, name, username, ciphertext or reveal URL.

A revealed DOM field can be cleared manually and is removed after 60 seconds or
on page exit. This reduces exposure; it cannot guarantee browser memory or
clipboard erasure. Authorized users can retain any value they have obtained.

See [encryption and backup](encryption.md) for the crucial key-rotation procedure
when a disabled or uninstalled plugin has retained encrypted records.
