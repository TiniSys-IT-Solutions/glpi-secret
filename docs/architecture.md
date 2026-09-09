# Architecture

The dependency direction is:

`GLPI UI/controllers -> application services -> security/domain -> persistence`

`Secret` is the single encrypted model. `SecretItem` links it to any compatible
GLPI object using one polymorphic N:N table. `SecretLog` contains append-only
audit metadata. `SecretAccessService` combines GLPI profile permission with the
ACL decision made by `AclPolicy`; callers must never reproduce that decision.

Ticket and asset integrations will supply contextual actor facts to the same ACL
engine. They will never duplicate encryption, audit, or authorization logic.

The 0.0.9 ITIL integration uses controllers discovered from `src/Controller/`.
User-facing plugin strings use the `secret` gettext domain and GLPI's native
`locales/<language>.mo` loading mechanism.
GLPI's controller listener authenticates the route and validates CSRF for POST
requests. `TimelineActionProvider` contributes the native answer action, while
`TicketSecretRepository` sends only authorized metadata to Twig.
Templates target GLPI's canonical `plugins/secret/...` web paths so controller
URLs work identically for manual and Marketplace installations.
The provider uses a plain form-context object and a stable HTML-safe action key;
it never instantiates an unsaved secret or embeds plaintext in the initial page.
