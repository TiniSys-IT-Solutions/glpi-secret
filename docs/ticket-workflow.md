# Ticket and ITIL workflow

Version 0.0.5 supports Ticket, Change, and Problem objects.

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
must both pass.

Expiration may be never, when the ITIL object is closed, after 1/7/30 days, or a
future custom date. Expired values remain listed as metadata when authorized but
cannot be revealed.

Global administrators with the Secret administration right can enable the
timeline action and choose default visibility, expiration, generator options,
and the maximum accepted secret length.

No followup or notification contains the value. Adding a secret produces only
the protected plugin record and its relation to the ITIL object.

GLPI serves the plugin stylesheet and JavaScript from `public/`; hook paths are
therefore registered as `css/secret.css` and `js/secret.js` without a duplicate
`public/` prefix.
