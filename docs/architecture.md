# Architecture

The dependency direction is:

`GLPI UI/controllers -> application services -> security/domain -> persistence`

`Secret` is the single encrypted model. `SecretItem` links it to any compatible
GLPI object using one polymorphic N:N table. `SecretLog` contains append-only
audit metadata. `SecretAccessService` combines GLPI profile permission with the
active-entity scope and the ACL decision made by `AclPolicy`; callers must never
reproduce that decision. Entity scope is resolved with GLPI's
`Session::haveAccessToEntity()` so parent/child access follows the user's active
profile assignment and its native recursive flag.

Ticket and asset integrations will supply contextual actor facts to the same ACL
engine. They will never duplicate encryption, audit, or authorization logic.

The 0.0.20 ITIL integration uses controllers discovered from `src/Controller/`.
User-facing plugin strings use the `secret` gettext domain and GLPI's native
`locales/<language>.mo` loading mechanism.
GLPI's controller listener authenticates the route and validates CSRF for POST
requests. `TimelineActionProvider` contributes the native answer action, while
`TicketSecretRepository` sends only authorized metadata to Twig.
Templates target GLPI's canonical `plugins/secret/...` web paths so controller
URLs work identically for manual and Marketplace installations.
The provider uses a plain form-context object and a stable HTML-safe action key;
it never instantiates an unsaved secret or embeds plaintext in the initial page.
At initialization the plugin registers its permission fields in GLPI's native
`Profile::$helpdesk_rights` allow-list. This is required because GLPI removes
all other rights when it loads a Helpdesk-interface profile.
Modern ITIL controllers explicitly select GLPI's authenticated firewall
strategy instead of the default Central-only strategy. Their own authorization
then enforces item access, profile permission, entity scope, ACL, and audit.

## ITIL hardening in 0.0.20

`ClosedGenericAccess` closes generic model surfaces. Internal persistence stays
inside the application services; generic CommonDBTM permission checks are not
used to authorize a service's already-verified operation. SQL metadata criteria
are produced by SecretAccessService so counts and paginated lists use the same
owner/group/actor policy as individual actions. Closure facts are batched per
service instance, never cached across requests or users.

`ExpirationLifecycle` records the first observed effective expiration in the
existing expiration column. Hooks observe closure, an already-closed item before
update, and parent purge. Maintenance reconciles closed or missing parents and
orphans in bounded batches, including old rows whose expiration is NULL. Once a
date is recorded it is never cleared on reopening. `ExpiredSecretPurger` retains
audit entries, commits each successful purge atomically, reports native CronTask
volume/errors and advances a cursor past failing rows.

The operator metadata tab uses pages of 50 authorized entries; the audit uses an
ID cursor and pages of 50. Timeline cards remain under native GLPI timeline
rendering; their metadata is batched, with no per-secret parent reload.
