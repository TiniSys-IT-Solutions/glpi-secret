<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonDBTM;
use Session;

final class AssetEntityScope
{
    /** @return list<int> */
    public function allowedSecretEntityIds(CommonDBTM $item): array
    {
        $entityId = (int) ($item->fields['entities_id'] ?? -1);
        if ($entityId < 0) {
            return [];
        }

        $entityIds = [$entityId];
        if (!empty($item->fields['is_recursive'])) {
            $entityIds = array_merge($entityIds, array_map('intval', getSonsOf('glpi_entities', $entityId)));
        }

        return array_values(array_filter(
            array_unique($entityIds),
            static fn(int $id): bool => Session::haveAccessToEntity($id),
        ));
    }

    public function allows(CommonDBTM $item, int $secretEntityId): bool
    {
        return in_array($secretEntityId, $this->allowedSecretEntityIds($item), true);
    }
}
