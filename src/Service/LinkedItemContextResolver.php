<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonDBTM;
use CommonITILObject;
use GlpiPlugin\Secret\Security\AclContext;
use GlpiPlugin\Secret\Security\AssetActorResolver;
use GlpiPlugin\Secret\Security\ItilActorResolver;

final class LinkedItemContextResolver
{
    /** @return array{0: CommonDBTM, 1: AclContext}|null */
    public function resolve(string $itemtype, int $itemsId): ?array
    {
        if ($itemsId <= 0) {
            return null;
        }
        $item = getItemForItemtype($itemtype);
        if (!$item instanceof CommonDBTM || !$item->getFromDB($itemsId) || !$item->canViewItem()) {
            return null;
        }
        if ($item instanceof CommonITILObject && in_array($itemtype, \GlpiPlugin\Secret\SecretItem::supportedItemtypes(), true)) {
            return [$item, (new ItilActorResolver())->forItem($item)];
        }
        if ((new AssetTypeProvider())->supports($itemtype)) {
            return [$item, (new AssetActorResolver())->forItem($item)];
        }
        return null;
    }
}
