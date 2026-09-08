<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Tests\Unit;

use GlpiPlugin\Secret\Security\GlpiKeyCipher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GlpiKeyCipherTest extends TestCase
{
    #[DataProvider('values')]
    public function testRoundTrip(string $value): void
    {
        $cipher = new GlpiKeyCipher();
        $encrypted = $cipher->encrypt($value);

        self::assertNotSame($value, $encrypted);
        self::assertSame($value, $cipher->decrypt($encrypted));
    }

    public static function values(): iterable
    {
        yield 'unicode' => ['Pässwörd-Été-ß-€-🔐'];
        yield 'special characters' => ["'\"<&>\\/\0\n\r\t"];
        yield 'very long' => [str_repeat('technical-secret-🔐', 1024)];
    }

    public function testEncryptionFailsClosedWhenGlpiKeyIsUnavailable(): void
    {
        \GLPIKey::$encryptionAvailable = false;
        try {
            $this->expectException(RuntimeException::class);
            (new GlpiKeyCipher())->encrypt('must-not-be-stored');
        } finally {
            \GLPIKey::$encryptionAvailable = true;
        }
    }

    public function testDecryptionFailsClosedWhenGlpiKeyIsUnavailable(): void
    {
        $encrypted = (new GlpiKeyCipher())->encrypt('must-not-be-returned');
        \GLPIKey::$readErrors = true;
        try {
            $this->expectException(RuntimeException::class);
            (new GlpiKeyCipher())->decrypt($encrypted);
        } finally {
            \GLPIKey::$readErrors = false;
        }
    }

    public function testDecryptionRejectsInvalidCiphertext(): void
    {
        $this->expectException(RuntimeException::class);
        (new GlpiKeyCipher())->decrypt('not-a-valid-ciphertext');
    }
}
