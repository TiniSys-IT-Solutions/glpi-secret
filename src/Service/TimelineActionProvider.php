<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonITILObject;
use GlpiPlugin\Secret\Config;
use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\SecretItem;

final class TimelineActionProvider
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, array<string, mixed>>
     */
    public static function actions(array $params = []): array
    {
        $item = $params['item'] ?? null;
        $config = Config::values();
        if (
            !$item instanceof CommonITILObject
            || $item->isNewItem()
            || !in_array($item->getType(), SecretItem::supportedItemtypes(), true)
            || !$item->canViewItem()
            || !$config['ticket_enabled']
            || !Profile::canCreateSecret()
        ) {
            return [];
        }

        return [
            'secret' => [
                'type' => 'PluginSecretSecret',
                'class' => 'PluginSecretSecret',
                'icon' => 'ti ti-key',
                'label' => __('Add a secret', 'secret'),
                'short_label' => __('Secret', 'secret'),
                'template' => '@secret/timeline_form.html.twig',
                'item' => (object) [
                    'itemtype' => $item->getType(),
                    'items_id' => (int) $item->getID(),
                    'entities_id' => (int) ($item->fields['entities_id'] ?? 0),
                    'config' => $config,
                ],
                'hide_in_menu' => false,
            ],
        ];
    }
}
