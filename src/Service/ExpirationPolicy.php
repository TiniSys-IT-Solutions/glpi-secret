<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use DateTimeImmutable;
use InvalidArgumentException;

final class ExpirationPolicy
{
    public const NEVER = 'never';
    public const TICKET_CLOSED = 'ticket_closed';
    public const ONE_DAY = 'one_day';
    public const SEVEN_DAYS = 'seven_days';
    public const THIRTY_DAYS = 'thirty_days';
    public const CUSTOM = 'custom';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::NEVER, self::TICKET_CLOSED, self::ONE_DAY, self::SEVEN_DAYS, self::THIRTY_DAYS, self::CUSTOM];
    }

    public function resolve(string $policy, ?string $customDate = null, ?DateTimeImmutable $now = null): ?string
    {
        $now ??= new DateTimeImmutable();

        return match ($policy) {
            self::NEVER, self::TICKET_CLOSED => null,
            self::ONE_DAY => $now->modify('+1 day')->format('Y-m-d H:i:s'),
            self::SEVEN_DAYS => $now->modify('+7 days')->format('Y-m-d H:i:s'),
            self::THIRTY_DAYS => $now->modify('+30 days')->format('Y-m-d H:i:s'),
            self::CUSTOM => $this->custom($customDate, $now),
            default => throw new InvalidArgumentException('Unsupported expiration policy.'),
        };
    }

    private function custom(?string $value, DateTimeImmutable $now): string
    {
        $expiration = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $value);
        if (!$expiration instanceof DateTimeImmutable || $expiration <= $now) {
            throw new InvalidArgumentException('The custom expiration must be a valid future date.');
        }

        return $expiration->format('Y-m-d H:i:s');
    }
}
