# Upgrade

Before every upgrade, back up the GLPI database and `glpicrypt.key`. The release
tag must match the version declared in `setup.php`. Install the new files and run
the standard GLPI plugin update action.

Schema changes are applied idempotently through GLPI migrations. Never alter or
re-encrypt `encrypted_value` outside GLPI's native key rotation workflow.

