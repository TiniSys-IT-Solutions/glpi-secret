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

## Upgrade to 0.0.21

The administrator visibility override is disabled by default, so upgrading does
not broaden existing access. If supervision access is required, enable it in
the Secret settings and verify that the intended Admin, Super-Admin or custom
profile holds **Administrer Secret** plus each required action right. Native
access to the linked ITIL object remains mandatory.

## Upgrade to 0.1.0

No schema or encrypted-value migration is required. The new profile rights
assistant is an explicit administration tool and changes no profile during the
upgrade. A user must hold GLPI's native profile update permission, select target
profiles, preview all seven resulting Secret rights, and apply the change.

## Upgrade to 0.1.1

This hotfix changes no database schema or ciphertext. It corrects the Secret
card type injected into GLPI's ITIL timeline so ticket update, resolution and
mass-action notifications can inspect the timeline without a missing-class
error. Notification timeline content for these cards remains explicitly empty.
The same 0.1.1 maintenance pass removes an obsolete configuration marker and
unregisters the automatic action on uninstall; encrypted records, dedicated
audit rows, plugin settings and profile choices remain retained.
