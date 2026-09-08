# Permissions

Secret adds independent GLPI profile permissions for metadata, create, reveal,
update, delete, audit, and administration. On first installation, profiles that
can update GLPI configuration receive these rights; all other profiles start
with no access.

Possessing a profile right does not bypass the per-secret ACL. Administrators
must also be included by the selected ACL rule. This prevents accidental global
disclosure through elevated UI access.

For ITIL objects, assigned technicians include direct assignees and users in an
assigned technician group. Requester visibility likewise includes direct
requesters and requester-group members. Access to the Ticket, Change, or Problem
itself is checked before listing, creation, reveal, or copy.
