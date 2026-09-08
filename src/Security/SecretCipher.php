<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

interface SecretCipher
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $ciphertext): string;
}
