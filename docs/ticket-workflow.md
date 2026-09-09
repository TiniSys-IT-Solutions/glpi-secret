# Ticket and ITIL workflow

Version 0.0.19 supports Ticket, Change, and Problem objects.

1. An authorized user opens **Secret** directly from GLPI's native **Reply**
   split button. The action is grouped as an ITIL followup so it behaves like
   the built-in Followup, Task, Solution, Document, and Approval actions.
2. The user enters or generates a value, selects visibility and expiration, and
   submits the form.
3. A GLPI 11 POST controller validates CSRF, object access, profile rights, input,
   encryption, relation creation, and the CREATE audit in one transaction.
4. Authorized metadata appears in the object's **Secrets (N)** tab.
5. Reveal and Copy each perform a new POST request and a complete authorization
   decision. Plaintext is never part of the initial HTML.

Visibility choices are owner only, assigned technicians, requesters plus
technicians, or an explicitly selected group. Profile permission and this ACL
must both pass. The user must also have GLPI access to the ITIL object's entity;
parent-to-child access is inherited only from a recursive GLPI profile/entity
assignment. A secret created from an ITIL object is scoped to that exact entity
and is never made recursively visible by the plugin.

Self-Service requesters need **Read secret metadata** to see authorized purple
Secret cards in the timeline and **Reveal secrets** to reveal or copy a value.
The full Secret tab stays hidden in Self-Service. Selecting requester-and-
technician visibility does not bypass those profile permissions.

On installation, every profile using GLPI's Helpdesk interface receives
metadata, create, and reveal rights. This includes custom profiles used by the
service catalogue. Built-in Hotliner, Observer, Technician, and Supervisor
profiles additionally receive update and delete rights. Profiles that can
administer GLPI receive every Secret right. These defaults never bypass the
per-secret ACL and fill only rights that remain zero during the one-time policy
migration.

Expiration may be never, when the ITIL object is closed, after 1/7/30 days, or a
future custom date. Expired values remain listed as metadata when authorized but
cannot be revealed.

Global administrators with the Secret administration right can enable the
timeline action and choose default visibility, expiration, generator options,
and the maximum accepted secret length.

After creation, the plugin adds a native public followup saying only that a
secure secret is available for the relevant ticket, change, or problem and that
the recipient must sign in to GLPI. GLPI therefore keeps control of notification
templates and recipients. The followup never contains the value, name, username,
reveal URL, token, or cryptographic material.

Separately, authorized users see a plugin-owned Secret card in the GLPI
timeline. This card never participates in notifications and contains no
plaintext until its Reveal button completes a new authorized and audited POST
request. Operators with mutation or audit rights also receive a Manage shortcut
to the full Secret tab.

In the Self-Service interface the plugin does not add its full metadata tab;
authorized requesters use only the purple Secret cards in the native timeline.
GLPI's native Ticket and All navigation entries remain controlled by GLPI.

Users with the corresponding profile and ACL rights may update metadata or
replace the encrypted value, delete a secret with explicit confirmation, and
inspect its CREATE/VIEW/COPY/UPDATE/DELETE/PURGE audit trail. Audit entries are
retained when the encrypted record is deleted.

The `purgeExpired` GLPI automatic action permanently removes expired encrypted
records after its configurable retention (30 days by default, zero disables
purging). It processes at most 500 records per run and does not require a
separate system cron.

GLPI serves the plugin stylesheet and JavaScript from `public/`; hook paths are
therefore registered as `css/secret.css` and `js/secret.js` without a duplicate
`public/` prefix.
Controller forms use the canonical `plugins/secret/...` URL namespace, including
when GLPI stores the plugin physically under `marketplace/secret`.
