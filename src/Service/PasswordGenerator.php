<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use InvalidArgumentException;

final class PasswordGenerator
{
    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const DIGITS = '23456789';
    private const SPECIAL = '!@#$%^&*()-_=+[]{}:,.?';
    private const AMBIGUOUS = 'Il1O0o';

    public function generate(
        int $length = 20,
        bool $lowercase = true,
        bool $uppercase = true,
        bool $digits = true,
        bool $special = true,
        bool $excludeAmbiguous = true,
    ): string {
        if ($length < 8 || $length > 256) {
            throw new InvalidArgumentException('Password length must be between 8 and 256.');
        }

        $sets = array_values(array_filter([
            $lowercase ? self::LOWER : null,
            $uppercase ? self::UPPER : null,
            $digits ? self::DIGITS : null,
            $special ? self::SPECIAL : null,
        ]));
        if ($sets === []) {
            throw new InvalidArgumentException('At least one character set must be enabled.');
        }

        if (!$excludeAmbiguous) {
            $sets[0] .= self::AMBIGUOUS;
        }

        $characters = [];
        foreach ($sets as $set) {
            $characters[] = $set[random_int(0, strlen($set) - 1)];
        }
        $pool = implode('', $sets);
        while (count($characters) < $length) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; --$index) {
            $swap = random_int(0, $index);
            [$characters[$index], $characters[$swap]] = [$characters[$swap], $characters[$index]];
        }

        return implode('', $characters);
    }
}
