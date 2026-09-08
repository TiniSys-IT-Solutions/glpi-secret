# Architecture

The dependency direction is:

`GLPI UI/controllers -> application services -> security/domain -> persistence`

`Secret` is the single encrypted model. `SecretItem` links it to any compatible
GLPI object using one polymorphic N:N table. `SecretLog` contains append-only
audit metadata. `SecretAccessService` combines GLPI profile permission with the
ACL decision made by `AclPolicy`; callers must never reproduce that decision.

Ticket and asset integrations will supply contextual actor facts to the same ACL
engine. They will never duplicate encryption, audit, or authorization logic.

The 0.0.2 ITIL integration uses controllers discovered from `src/Controller/`.
GLPI's controller listener authenticates the route and validates CSRF for POST
requests. `TimelineActionProvider` contributes the native answer action, while
`TicketSecretRepository` sends only authorized metadata to Twig.
