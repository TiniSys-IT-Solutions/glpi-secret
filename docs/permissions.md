# Permissions

Secret adds independent GLPI profile permissions for metadata, create, reveal,
update, delete, audit, and administration. On installation, Helpdesk-interface
profiles receive metadata, create, and reveal rights; built-in ITIL operator
profiles additionally receive update and delete rights; profiles that can
administer GLPI receive every Secret right. Administrators can subsequently
change these defaults per profile.

The plugin declares all of its permission fields in GLPI's native Helpdesk
rights allow-list. Consequently, a Self-Service session receives the exact
Secret values stored for its active profile; merely declaring database rows is
not sufficient because GLPI filters Helpdesk profiles while loading them.
The plugin also refreshes only its own permission fields in the active profile
during initialization, covering sessions GLPI constructed before plugin hooks
were registered without broadening any right stored by the administrator.

For linked ITIL actions, GLPI's native `canViewItem()` result is the authoritative
item-scope check. This intentionally supports a requester who can view their
catalogue ticket as an actor even when its processing entity is not part of
their directly active entity set. The Secret profile right and per-secret actor
ACL must still pass. Direct access outside a verified linked ITIL context keeps
the stricter active-entity requirement.

Possessing a profile right does not bypass either GLPI's entity scope or the
per-secret ACL. Administrators must also be included by the selected ACL rule.
This prevents accidental global disclosure through elevated UI access.

ITIL secrets are created in the exact entity of their Ticket, Change, or
Problem and are not themselves recursive. Access nonetheless follows the
official GLPI profile assignment: a user assigned directly to that entity can
act there; a user assigned to a parent can act in the child only when that GLPI
assignment is recursive. Selecting a parent entity without recursive access
does not expose secrets from child entities. The current active entity set is
honoured, so switching entity can change which secrets are visible.

For ITIL objects, assigned technicians include direct assignees and users in an
assigned technician group. Requester visibility likewise includes direct
requesters and requester-group members. Access to the Ticket, Change, or Problem
itself is checked before listing, creation, reveal, or copy.
