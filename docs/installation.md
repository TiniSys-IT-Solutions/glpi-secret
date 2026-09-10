# Installation

The release ZIP contains one top-level `secret/` directory. Extract it into the
GLPI `plugins/` directory, then install and activate **Secret** from GLPI.

Version 0.0.20 supports GLPI 11.0.8 through 11.0.x and PHP 8.2 or newer. Confirm
that Sodium is enabled and back up both the database and `glpicrypt.key` before
installation or upgrade.

Uninstalling 0.0.20 disables the plugin but intentionally retains its database
tables and encrypted values. This avoids irreversible deletion without informed
confirmation.

Retained data still depends on its original encryption key. Read the
[rotation procedure](encryption.md#rotation-with-retained-plugin-data) before
rotating GLPI's key while Secret is disabled or uninstalled.
