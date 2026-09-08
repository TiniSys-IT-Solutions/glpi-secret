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
