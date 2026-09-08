<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists('GLPIKey')) {
    final class GLPIKey
    {
        public static bool $encryptionAvailable = true;
        public static bool $readErrors = false;

        public function encrypt(string $value): string
        {
            return self::$encryptionAvailable ? 'test:' . base64_encode($value) : '';
        }

        public function decrypt(?string $value): ?string
        {
            if (self::$readErrors) {
                return $value;
            }
            if ($value === null || !str_starts_with($value, 'test:')) {
                return '';
            }

            $decoded = base64_decode(substr($value, 5), true);
            return $decoded === false ? '' : $decoded;
        }

        public function hasReadErrors(): bool
        {
            return self::$readErrors;
        }
    }
}
