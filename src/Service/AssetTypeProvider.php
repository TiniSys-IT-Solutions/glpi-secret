<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

final class AssetTypeProvider
{
    /** @return list<class-string<\CommonDBTM>> */
    public function all(): array
    {
        global $CFG_GLPI;

        $types = [];
        foreach (($CFG_GLPI['asset_types'] ?? []) as $type) {
            if (is_string($type) && class_exists($type) && is_a($type, \CommonDBTM::class, true)) {
                $types[] = $type;
            }
        }
        return array_values(array_unique($types));
    }

    public function supports(string $type): bool
    {
        return in_array($type, $this->all(), true);
    }
}
