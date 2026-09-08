# Installation

The release ZIP contains one top-level `secret/` directory. Extract it into the
GLPI `plugins/` directory, then install and activate **Secret** from GLPI.

Version 0.0.1 supports GLPI 11.0.8 through 11.0.x and PHP 8.2 or newer. Confirm
that Sodium is enabled and back up both the database and `glpicrypt.key` before
installation or upgrade.

Uninstalling 0.0.1 disables the plugin but intentionally retains its database
tables and encrypted values. This avoids irreversible deletion without informed
confirmation.

