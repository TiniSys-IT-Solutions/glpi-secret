<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

final readonly class AclContext
{
    /** @param list<int> $groupIds */
    public function __construct(
        public int $userId,
        public array $groupIds = [],
        public bool $ticketTechnician = false,
        public bool $ticketRequester = false,
        public bool $entityTechnician = false,
        public bool $itilItemAccess = false,
    ) {}
}
