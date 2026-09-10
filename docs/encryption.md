# Encryption and backup

GLPI 11.0.8 `GLPIKey` encrypts values using libsodium XChaCha20-Poly1305 with a
fresh random nonce and the key stored in `glpicrypt.key`. The plugin registers
`glpi_plugin_secret_secrets.encrypted_value` as a secured field so GLPI's key
rotation migrates every ciphertext.

If GLPI cannot read its key, `GLPIKey::encrypt()` returns an empty value. Secret
treats this as a hard failure and does not write the record.

Decryption also fails closed when the key cannot be read, the ciphertext cannot
be authenticated, or GLPI returns the ciphertext unchanged. No value is sent to
the client in those cases.

Restore the database and its matching `glpicrypt.key` together. Losing the key
makes existing secrets unrecoverable. The key must never be copied into plugin
files, database configuration, archives, or logs.

## Rotation with retained plugin data

The secured-fields hook is available when Secret is loaded. GLPI's normal plugin
boot does not initialize disabled or uninstalled plugins. Keeping encrypted
tables after uninstall therefore does not guarantee that a later native key
rotation will migrate those tables.

Before rotating the GLPI storage key:

1. Back up the complete database and its matching glpicrypt.key together.
2. Inventory retained encrypted plugin tables. If Secret data exists, reinstall
   if needed and activate a compatible Secret version before rotation.
3. Run GLPI's native `php bin/console security:change_key` procedure in the normal
   environment with plugin execution enabled. Do not rotate with Secret disabled,
   its files removed, or plugin execution suspended.
4. Verify an authorized synthetic secret before and after rotation, then create
   a new matched database/key backup. Only then disable the plugin if required.

If rotation already occurred while Secret was unavailable, do not attempt random
keys or write over retained values. Preserve both states and recover from the
matched pre-rotation database/key backup in an isolated instance before planning
restoration. The plugin does not retain old keys or implement custom rekeying.
Historical backup pairs are sensitive recovery material and must be protected.
