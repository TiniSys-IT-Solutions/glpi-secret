<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonITILObject;
use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\SecretItem;

final class TimelineItemProvider
{
    /** @param array<string, mixed> $params */
    public static function items(array $params): void
    {
        $item = $params['item'] ?? null;
        if (!$item instanceof CommonITILObject || !$item->canViewItem() || !Profile::canReadMetadata()) {
            return;
        }
        $timeline = &$params['timeline'];
        if (!is_array($timeline)) {
            return;
        }
        $manageUrl = $item->getLinkURL()
            . (str_contains($item->getLinkURL(), '?') ? '&' : '?')
            . 'forcetab=' . rawurlencode(SecretItem::getType() . '$1');

        foreach ((new TicketSecretRepository())->visibleMetadataForItem($item) as $secret) {
            $timeline['PluginSecretTimelineSecret_' . $secret['id']] = [
                'type' => 'PluginSecretTimelineSecret',
                'class' => 'plugin-secret-timeline-item',
                'item' => [
                    ...$secret,
                    'users_id' => $secret['users_id_creator'],
                    'date' => $secret['date_creation'],
                    'date_mod' => $secret['date_mod'],
                    'timeline_position' => CommonITILObject::TIMELINE_RIGHT,
                    'itemtype' => $item->getType(),
                    'items_id' => (int) $item->getID(),
                    'manage_url' => $manageUrl,
                    'can_manage' => $secret['can_update'] || $secret['can_delete'] || $secret['can_audit'],
                ],
            ];
        }
    }
}
