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
}
