<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonITILObject;
use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\Security\AclContext;
use GlpiPlugin\Secret\Security\AclPolicy;
use GlpiPlugin\Secret\Security\ItilActorResolver;
use GlpiPlugin\Secret\Security\Visibility;
use Session;

final class SecretAccessService
{
    public function __construct(
        private readonly AclPolicy $policy = new AclPolicy(),
        private readonly ItilActorResolver $itilActors = new ItilActorResolver(),
    ) {}

    public function canSeeMetadata(Secret $secret, ?AclContext $context = null): bool
    {
        return Profile::canReadMetadata() && $this->aclAllows($secret, $context);
    }

    public function canCreate(): bool
    {
        return Profile::canCreateSecret();
    }

    public function canCreateInEntity(int $entityId): bool
    {
        return $this->canCreate() && Session::haveAccessToEntity($entityId);
    }

    public function canCreateForItil(CommonITILObject $item): bool
    {
        return $this->canCreate() && $item->canViewItem();
    }

    public function canReveal(Secret $secret, ?AclContext $context = null): bool
    {
        return Profile::canRevealSecret() && !$this->isExpired($secret) && $this->aclAllows($secret, $context);
    }

    public function canUpdate(Secret $secret, ?AclContext $context = null): bool
    {
        return Profile::canUpdateSecret() && $this->aclAllows($secret, $context);
    }

    public function canDelete(Secret $secret, ?AclContext $context = null): bool
    {
        return Profile::canDeleteSecret() && $this->aclAllows($secret, $context);
    }

    public function canAudit(Secret $secret, ?AclContext $context = null): bool
    {
        return Profile::canViewAudit() && $this->aclAllows($secret, $context);
    }

    public function canAdminister(): bool
    {
        return Profile::canAdminister();
    }

    private function aclAllows(Secret $secret, ?AclContext $context = null): bool
    {
        $visibility = (string) ($secret->fields['visibility'] ?? '');
        $entityId = (int) ($secret->fields['entities_id'] ?? 0);
        $entityAllowed = Session::haveAccessToEntity(
            $entityId,
            (bool) ($secret->fields['is_recursive'] ?? false),
        );
        // For an ITIL-bound request, the linked object's native canViewItem()
        // decision is authoritative. This covers legitimate requesters from
        // the service catalogue even when the ticket entity is outside their
        // directly active entity set. Direct Secret access still requires the
        // regular GLPI entity scope.
        if (!$entityAllowed && !($context->itilItemAccess ?? false)) {
            return false;
        }

        $context ??= in_array($visibility, [Visibility::TICKET_TECHNICIANS, Visibility::REQUESTERS_AND_TECHNICIANS], true)
            ? $this->itilActors->forSecret($secret)
            : new AclContext(
                userId: (int) Session::getLoginUserID(),
                groupIds: array_map('intval', $_SESSION['glpigroups'] ?? []),
                entityTechnician: $visibility === Visibility::ENTITY_TECHNICIANS,
            );

        return $this->policy->allows(
            $visibility,
            (int) ($secret->fields['users_id_creator'] ?? 0),
            (int) ($secret->fields['groups_id'] ?? 0),
            $context,
        );
    }

    private function isExpired(Secret $secret): bool
    {
        if (($secret->fields['expiration_policy'] ?? '') === ExpirationPolicy::TICKET_CLOSED) {
            return $this->itilActors->hasClosedLinkedItem($secret);
        }
        $expiration = $secret->fields['expiration'] ?? null;
        return is_string($expiration) && $expiration !== '' && strtotime($expiration) <= time();
    }
}
