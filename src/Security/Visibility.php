<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

final class Visibility
{
    public const OWNER = 'owner';
    public const TICKET_TECHNICIANS = 'ticket_technicians';
    public const REQUESTERS_AND_TECHNICIANS = 'requesters_and_technicians';
    public const GROUP = 'group';
    public const ENTITY_TECHNICIANS = 'entity_technicians';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::OWNER,
            self::TICKET_TECHNICIANS,
            self::REQUESTERS_AND_TECHNICIANS,
            self::GROUP,
            self::ENTITY_TECHNICIANS,
        ];
    }

    public static function isValid(string $visibility): bool
    {
        return in_array($visibility, self::all(), true);
    }
}
