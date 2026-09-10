<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Tests\Unit;

use DateTimeImmutable;
use GlpiPlugin\Secret\Service\ExpirationPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ExpirationPolicyTest extends TestCase
{
    public function testRelativePolicies(): void
    {
        $policy = new ExpirationPolicy();
        $now = new DateTimeImmutable('2026-09-08 12:00:00');

        self::assertNull($policy->resolve(ExpirationPolicy::NEVER, null, $now));
        self::assertNull($policy->resolve(ExpirationPolicy::TICKET_CLOSED, null, $now));
        self::assertSame('2026-09-09 12:00:00', $policy->resolve(ExpirationPolicy::ONE_DAY, null, $now));
        self::assertSame('2026-09-15 12:00:00', $policy->resolve(ExpirationPolicy::SEVEN_DAYS, null, $now));
        self::assertSame('2026-10-08 12:00:00', $policy->resolve(ExpirationPolicy::THIRTY_DAYS, null, $now));
    }

    public function testRejectsImpossibleCalendarDates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExpirationPolicy())->resolve('custom', '2027-02-31T12:00', new DateTimeImmutable('2026-09-10'));
    }

    public function testMinutePrecisionDoesNotInheritCurrentSeconds(): void
    {
        self::assertSame('2027-02-28 12:00:00', (new ExpirationPolicy())->resolve(
            'custom',
            '2027-02-28T12:00',
            new DateTimeImmutable('2026-09-10 11:00:49'),
        ));
    }

    public function testCustomDateMustBeFuture(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExpirationPolicy())->resolve(
            ExpirationPolicy::CUSTOM,
            '2026-09-07T12:00',
            new DateTimeImmutable('2026-09-08 12:00:00'),
        );
    }
}
