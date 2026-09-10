# Upgrade

Before every upgrade, back up the GLPI database and `glpicrypt.key`. The release
tag must match the version declared in `setup.php`. Install the new files and run
the standard GLPI plugin update action.

Schema changes are applied idempotently through GLPI migrations. Never alter or
re-encrypt `encrypted_value` outside GLPI's native key rotation workflow.


## Upgrade to 0.0.20

Generic Secret/SecretItem/SecretLog API and search access is deliberately closed.
Use the dedicated ITIL UI. Existing encrypted values and audit rows are retained.
No ciphertext migration is needed; closure expiration uses the existing
expiration column. An idempotent index supports closure maintenance queries. The maintenance action reconciles pending closed
parents in bounded batches, so a backlog may take several runs.

Existing profile values, including deliberately revoked rights, are preserved.
After upgrade, validate requester and technician access, a denied profile, a
closed/reopened ticket, purge retention and notification permission in a test
instance. Keep the database and matching key backup until this validation passes.
