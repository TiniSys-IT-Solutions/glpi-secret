<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

final class Visibility
{
    public const OWNER = 'owner';
    public const TICKET_TECHNICIANS = 'ticket_technicians';
    public const REQUESTERS_AND_TECHNICIANS = 'requesters_and_technicians';
    public const GROUP = 'group';
    public const ASSET_TECHNICAL_PROFILES = 'asset_technical_profiles';

    public static function label(string $visibility): string
    {
        return match ($visibility) {
            self::OWNER => __('Me only', 'secret'),
            self::TICKET_TECHNICIANS => __('Assigned technicians', 'secret'),
            self::REQUESTERS_AND_TECHNICIANS => __('Requester and technicians', 'secret'),
            self::GROUP => __('Group'),
            self::ASSET_TECHNICAL_PROFILES => __('Authorized asset users', 'secret'),
            default => __('Unknown'),
        };
    }
}
