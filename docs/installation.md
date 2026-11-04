# Installation

The release ZIP contains one top-level `secret/` directory. Extract it into the
GLPI `plugins/` directory, then install and activate **Secret** from GLPI.

Version 0.3.0 supports GLPI 12.0.8 through 11.0.x and PHP 8.3 or newer. Confirm
that Sodium is enabled and back up both the database and `glpicrypt.key` before
installation or upgrade.

Uninstalling 0.3.0 disables the plugin but intentionally retains its database
tables and encrypted values. This avoids irreversible deletion without informed
confirmation. It unregisters the automatic action so GLPI cannot invoke plugin
code after uninstall; reinstalling registers it again.

Retained data still depends on its original encryption key. Read the
[rotation procedure](encryption.md#rotation-with-retained-plugin-data) before
rotating GLPI's key while Secret is disabled or uninstalled.
