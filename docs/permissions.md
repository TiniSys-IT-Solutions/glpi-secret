# Permissions

Secret has independent metadata, create, reveal, update, delete, audit and
administration profile rights. Every ITIL decision also requires the secret ACL
and access to the verified parent object. Being a GLPI administrator alone does
not authorize reading, changing or deleting another user's private secret.

On first installation, missing rights receive these defaults: Helpdesk profiles
receive metadata/create/reveal; built-in ITIL operator profiles also receive
update/delete; profiles able to administer GLPI receive each dedicated Secret
right. Existing rows, including zero (an intentional revocation), are never
changed by default synchronization. Upgrades and retained-data reinstallations
do not reapply the former one-time defaults to revoked rights.

Secret registers its rights in GLPI's Helpdesk allow-list and refreshes them from
the active profile's stored values. This repairs filtered Self-Service sessions
without granting rights not present in the database.

For a linked Ticket, Change or Problem, GLPI's native canViewItem decision defines
object access. A catalogue requester can therefore access their own ticket even
when its processing entity is outside their directly active entities. This is
not a general recursive-entity grant: profile rights and secret ACL still apply.
Outside a verified ITIL context, native active-entity/recursive checks apply.

Assigned-technician visibility includes direct assignees and users belonging to
assigned groups; requester visibility likewise follows native requester users
and groups. Owner visibility grants only the creator, and group visibility
requires membership in the selected group. Creation does not guarantee that its
author can later reveal a secret sent only to assigned technicians.

Adding the generic notification followup additionally requires GLPI's own
followup creation rights. Lack of that permission does not undo the saved secret.

All generic APIs, searches and model CRUD/purge access are closed for these three
plugin models in 0.0.20. Operator UI actions use the dedicated audited services.
Configuration retains its deliberate recovery exception: GLPI config UPDATE or
Secret administration UPDATE permits changing plugin defaults, but neither
bypasses the secret ACL. The plugin configuration still resides in native GLPI
General Setup and follows that page's own access restrictions.

The global **Allow Secret administrators to access all secrets** option is
disabled by default. When enabled, it lets profiles holding **Administrer
Secret** bypass the owner/group/ticket-actor visibility rule. It does not bypass
GLPI access to the linked Ticket, Change or Problem, expiration, or the separate
metadata/reveal/update/delete/audit profile right required by the requested
action. The implementation checks the dedicated right rather than profile names,
so it supports Admin, Super-Admin and deliberately authorized custom profiles.
