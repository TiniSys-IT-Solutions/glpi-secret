<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

use RuntimeException;

final class GlpiKeyCipher implements SecretCipher
{
    public function encrypt(string $plaintext): string
    {
        $ciphertext = (new \GLPIKey())->encrypt($plaintext);
        if ($ciphertext === '') {
            throw new RuntimeException('GLPI cryptographic key is unavailable; the secret was not stored.');
        }

        return $ciphertext;
    }

    public function decrypt(string $ciphertext): string
    {
        if ($ciphertext === '') {
            throw new RuntimeException('The encrypted secret is empty.');
        }

        $plaintext = (new \GLPIKey())->decrypt($ciphertext);
        if ($plaintext === null) {
            throw new RuntimeException('The secret could not be decrypted with the current GLPI key.');
        }

        return $plaintext;
    }
}
