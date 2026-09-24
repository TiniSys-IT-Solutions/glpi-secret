<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

use CommonDBTM;
use GlpiPlugin\Secret\Config;
use Session;

final class AssetActorResolver
{
    public function forItem(CommonDBTM $item): AclContext
    {
        return new AclContext(
            userId: (int) Session::getLoginUserID(),
            groupIds: array_map('intval', $_SESSION['glpigroups'] ?? []),
            assetItemAccess: $item->canViewItem(),
            assetTechnicalProfile: Config::activeProfileCanAccessAssets(),
        );
    }
}
