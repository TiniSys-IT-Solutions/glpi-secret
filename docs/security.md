# Security model

Authorization is an intersection, never a union:

`required profile right AND secret ACL AND non-expired state (for reveal)`

The owner, explicit group, ticket actors, and entity technicians are modeled as
ACL contexts. Unknown visibility values and incomplete contexts fail closed.

`encrypted_value` is listed in `CommonDBTM::$undisclosedFields`, omitted from
search options, and blacklisted from GLPI history. Application audit accepts
only allow-listed contextual keys and never arbitrary payloads.

No server-generated page or generic API response may contain plaintext. A
dedicated POST controller with session, CSRF, ACL, expiration, and audit checks
is the only HTTP reveal path. Its JSON response is marked `no-store` and contains
only the requested plaintext value. Copy performs a fresh authorized request so
the server can record a distinct COPY audit event. If the VIEW or COPY audit
cannot be persisted, the service fails closed and does not return the value.
