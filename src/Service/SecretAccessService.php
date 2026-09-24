<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonDBTM;
use CommonITILObject;
use GlpiPlugin\Secret\Config;
use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\Security\AclContext;
use GlpiPlugin\Secret\Security\AclPolicy;
use GlpiPlugin\Secret\Security\ItilActorResolver;
use GlpiPlugin\Secret\Security\Visibility;
use Session;

final class SecretAccessService
{
    /** @var array<int, bool> */
    private array $expired = [];

    /** @param list<int> $ids */
    public function primeExpirations(array $ids): void
    {
        $pending = (new ExpirationLifecycle())->pendingExpirations($ids, count($ids));
        foreach ($ids as $id) {
            $this->expired[$id] = isset($pending[$id]);
        }
    }

    public function __construct(
        private readonly AclPolicy $policy = new AclPolicy(),
        private readonly ItilActorResolver $itilActors = new ItilActorResolver(),
    ) {}

    public function canSeeMetadata(Secret $secret, ?AclContext $context = null): bool
    {
        return Profile::canReadMetadata() && $this->aclAllows($secret, $context);
    }

    /** @return array<string, mixed> */
    public function metadataCriteriaForItil(AclContext $context): array
    {
        $table = Secret::getTable();
        if (!Profile::canReadMetadata() || !$context->itilItemAccess || $context->userId <= 0) {
            return ["$table.id" => -1];
        }
        if ($this->administratorAclBypassEnabled()) {
            return ["$table.id" => ['>', 0]];
        }
        $clauses = [["$table.visibility" => Visibility::OWNER, "$table.users_id_creator" => $context->userId]];
        if ($context->groupIds !== []) {
            $clauses[] = ["$table.visibility" => Visibility::GROUP, "$table.groups_id" => $context->groupIds];
        }
        if ($context->ticketTechnician) {
            $clauses[] = ["$table.visibility" => Visibility::TICKET_TECHNICIANS];
        }
        if ($context->ticketRequester || $context->ticketTechnician) {
            $clauses[] = ["$table.visibility" => Visibility::REQUESTERS_AND_TECHNICIANS];
        }
        return ['OR' => $clauses];
    }

    /** @return array<string, mixed> */
    public function metadataCriteriaForAsset(AclContext $context): array
    {
        $table = Secret::getTable();
        if (!Profile::canReadMetadata() || !$context->assetItemAccess || $context->userId <= 0) {
            return ["$table.id" => -1];
        }
        if ($this->administratorAclBypassEnabled()) {
            return ["$table.id" => ['>', 0]];
        }
        $clauses = [["$table.visibility" => Visibility::OWNER, "$table.users_id_creator" => $context->userId]];
        if ($context->groupIds !== []) {
            $clauses[] = ["$table.visibility" => Visibility::GROUP, "$table.groups_id" => $context->groupIds];
        }
        if ($context->assetTechnicalProfile) {
            $clauses[] = ["$table.visibility" => Visibility::ASSET_TECHNICAL_PROFILES];
        }
        return ['OR' => $clauses];
    }

    public function canCreate(): bool
    {
        return Profile::canCreateSecret();
    }

    public function canCreateForItil(CommonITILObject $item): bool
    {
        return $this->canCreate() && $item->canViewItem();
    }

    public function canCreateForAsset(CommonDBTM $item): bool
    {
        return Config::values()['asset_enabled'] && $this->canCreate() && $item->canViewItem()
            && (new AssetTypeProvider())->supports($item->getType());
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
        // In a verified linked-object request, native canViewItem() is the
        // scope authority. This covers legitimate catalogue requesters and
        // assets visible through the active profile. Direct Secret access
        // still requires GLPI's regular entity scope.
        if (!$entityAllowed && !($context->itilItemAccess ?? false) && !($context->assetItemAccess ?? false)) {
            return false;
        }
        if ($this->administratorAclBypassEnabled()) {
            return true;
        }

        $context ??= in_array($visibility, [Visibility::TICKET_TECHNICIANS, Visibility::REQUESTERS_AND_TECHNICIANS], true)
            ? $this->itilActors->forSecret($secret)
            : new AclContext(
                userId: (int) Session::getLoginUserID(),
                groupIds: array_map('intval', $_SESSION['glpigroups'] ?? []),
            );

        return $this->policy->allows(
            $visibility,
            (int) ($secret->fields['users_id_creator'] ?? 0),
            (int) ($secret->fields['groups_id'] ?? 0),
            $context,
        );
    }

    private function administratorAclBypassEnabled(): bool
    {
        return (bool) Config::values()['admin_acl_bypass'] && Profile::canAdminister();
    }

    private function isExpired(Secret $secret): bool
    {
        $expiration = $secret->fields['expiration'] ?? null;
        if (is_string($expiration) && $expiration !== '') {
            return strtotime($expiration) <= time();
        }
        if (($secret->fields['expiration_policy'] ?? '') !== ExpirationPolicy::TICKET_CLOSED) {
            return false;
        }
        $id = (int) $secret->getID();
        if (array_key_exists($id, $this->expired)) {
            return $this->expired[$id];
        }
        $pending = (new ExpirationLifecycle())->pendingExpirations([$id], 1);
        if (isset($pending[$id])) {
            ExpirationLifecycle::persist($id, $pending[$id]);
            return true;
        }
        return false;
    }
}
