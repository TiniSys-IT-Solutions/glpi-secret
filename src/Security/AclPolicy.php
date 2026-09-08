<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

final class AclPolicy
{
    public function allows(string $visibility, int $creatorId, int $groupId, AclContext $actor): bool
    {
        if ($actor->userId <= 0) {
            return false;
        }

        return match ($visibility) {
            Visibility::OWNER => $actor->userId === $creatorId,
            Visibility::GROUP => $groupId > 0 && in_array($groupId, $actor->groupIds, true),
            Visibility::TICKET_TECHNICIANS => $actor->ticketTechnician,
            Visibility::REQUESTERS_AND_TECHNICIANS => $actor->ticketTechnician || $actor->ticketRequester,
            Visibility::ENTITY_TECHNICIANS => $actor->entityTechnician,
            default => false,
        };
    }
}
