<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

use CommonITILActor;
use CommonITILObject;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use Session;

final class ItilActorResolver
{
    public function forItem(CommonITILObject $item): AclContext
    {
        $userId = (int) Session::getLoginUserID();
        $canView = $item->canViewItem();

        return new AclContext(
            userId: $userId,
            groupIds: array_map('intval', $_SESSION['glpigroups'] ?? []),
            ticketTechnician: $canView
                && in_array($userId, array_map('intval', $item->getAllUsers(CommonITILActor::ASSIGN)), true),
            ticketRequester: $canView
                && in_array($userId, array_map('intval', $item->getAllUsers(CommonITILActor::REQUESTER)), true),
            itilItemAccess: $canView,
        );
    }

    public function forSecret(Secret $secret): AclContext
    {
        global $DB;

        $merged = new AclContext(
            userId: (int) Session::getLoginUserID(),
            groupIds: array_map('intval', $_SESSION['glpigroups'] ?? []),
        );
        foreach ($DB->request([
            'FROM' => SecretItem::getTable(),
            'WHERE' => ['plugin_secret_secrets_id' => (int) $secret->getID(), 'itemtype' => SecretItem::supportedItemtypes()],
        ]) as $relation) {
            $item = getItemForItemtype((string) $relation['itemtype']);
            if (!$item instanceof CommonITILObject || !$item->getFromDB((int) $relation['items_id'])) {
                continue;
            }
            $context = $this->forItem($item);
            $merged = new AclContext(
                userId: $merged->userId,
                groupIds: $merged->groupIds,
                ticketTechnician: $merged->ticketTechnician || $context->ticketTechnician,
                ticketRequester: $merged->ticketRequester || $context->ticketRequester,
                itilItemAccess: $merged->itilItemAccess || $context->itilItemAccess,
            );
        }

        return $merged;
    }

    public function hasClosedLinkedItem(Secret $secret): bool
    {
        global $DB;

        foreach ($DB->request([
            'FROM' => SecretItem::getTable(),
            'WHERE' => ['plugin_secret_secrets_id' => (int) $secret->getID(), 'itemtype' => SecretItem::supportedItemtypes()],
        ]) as $relation) {
            $item = getItemForItemtype((string) $relation['itemtype']);
            if ($item instanceof CommonITILObject && $item->getFromDB((int) $relation['items_id']) && $item->isClosed()) {
                return true;
            }
        }

        return false;
    }
}
