<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonITILObject;
use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\TimelineSecret;

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
                'type' => TimelineSecret::class,
                'class' => 'plugin-secret-timeline-item',
                'item' => [
                    ...$secret,
                    // GLPI notification targets expect every timeline item to
                    // expose content. Keep it deliberately empty: notification
                    // emails must contain no secret metadata or plaintext.
                    'content' => '',
                    'is_content_safe' => false,
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
