# Native GLPI integration checks

This runner executes native GLPI classes and SQL against a **disposable** GLPI
11.0.8 instance. It requires no Docker and must never target production or a copy
containing real credentials. It changes configuration and rotates the test key.
Test rows are rolled back and the original test key is restored in a finally
block, but the instance must remain disposable in case a process is interrupted.

1. Install GLPI 11.0.8 with its required PHP extensions and a local MariaDB/MySQL
   database. Disable telemetry and notifications; use only synthetic data.
2. Install and activate this plugin under `plugins/secret`.
3. Create an empty `.secret-disposable-test` marker at the GLPI root.
4. Run `SECRET_TEST_GLPI_ROOT=/absolute/disposable/glpi php tests/Integration/itil.php`
   with PHP configured for that database. The runner uses the fresh-install GLPI
   administrator ID 2 and native session initialization.

The checks execute generic API list/create methods (only response transport is
intercepted), native CSRF checks, dedicated audit/reveal controllers, real
GLPIKey encryption and rotation, profile synchronization, SQL ACL equivalence,
invalid inputs, notification rights, audit-failure rollback, closure/reopening
and retained purge audit for Ticket, Change and Problem.

Key-registration lifecycle checks can be run in separate processes after
changing the disposable plugin state:

- disabled/uninstalled: set `SECRET_TEST_KEY_REGISTRATION=0`;
- installed and active: set `SECRET_TEST_KEY_REGISTRATION=1`.

These modes only check field registration and do not rotate a disabled plugin's
key. For retained data, follow `docs/encryption.md` before rotating any real key.
The runner tests native backend behavior; it does not replace browser testing of
Central/Self-Service layouts or reverse-proxy deployment.
