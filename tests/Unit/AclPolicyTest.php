<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Tests\Unit;

use GlpiPlugin\Secret\Security\AclContext;
use GlpiPlugin\Secret\Security\AclPolicy;
use GlpiPlugin\Secret\Security\Visibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AclPolicyTest extends TestCase
{
    #[DataProvider('accessCases')]
    public function testAclDecisions(string $visibility, int $creator, int $group, AclContext $actor, bool $expected): void
    {
        self::assertSame($expected, (new AclPolicy())->allows($visibility, $creator, $group, $actor));
    }

    public static function accessCases(): iterable
    {
        yield 'owner' => [Visibility::OWNER, 7, 0, new AclContext(7), true];
        yield 'not owner' => [Visibility::OWNER, 7, 0, new AclContext(8), false];
        yield 'group member' => [Visibility::GROUP, 7, 12, new AclContext(8, [12]), true];
        yield 'other group' => [Visibility::GROUP, 7, 12, new AclContext(8, [13]), false];
        yield 'ticket technician' => [Visibility::TICKET_TECHNICIANS, 7, 0, new AclContext(8, [], true), true];
        yield 'requester' => [Visibility::REQUESTERS_AND_TECHNICIANS, 7, 0, new AclContext(8, [], false, true), true];
        yield 'entity technician' => [Visibility::ENTITY_TECHNICIANS, 7, 0, new AclContext(8, [], false, false, true), true];
        yield 'anonymous always denied' => [Visibility::OWNER, 0, 0, new AclContext(0), false];
        yield 'unknown visibility denied' => ['unexpected', 7, 0, new AclContext(7), false];
    }
}
