<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Tests\Unit;

use GlpiPlugin\Secret\Service\AuditLogger;
use PHPUnit\Framework\TestCase;

final class AuditLoggerTest extends TestCase
{
    public function testContextIsStrictlyAllowListed(): void
    {
        $context = (new AuditLogger())->sanitizeContext([
            'source' => 'ticket',
            'items_id' => 42,
            'secret' => 'must-never-be-logged',
            'encrypted_value' => 'must-never-be-logged',
            'nested' => ['password' => 'must-never-be-logged'],
        ]);

        self::assertSame(['source' => 'ticket', 'items_id' => 42], $context);
    }
}
