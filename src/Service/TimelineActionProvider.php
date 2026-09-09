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
            || (!Profile::canCreateSecret() && !Profile::canReadMetadata())
        ) {
            return [];
        }

        // GLPI only hands this value back to our Twig template as `subitem`.
        // A data holder avoids pretending that an unsaved secret already exists.
        $subitem = (object) ['fields' => [
            'itemtype' => $item->getType(),
            'items_id' => (int) $item->getID(),
            'entities_id' => (int) ($item->fields['entities_id'] ?? 0),
            'config' => $config,
        ]];

        $canCreate = (new SecretAccessService())->canCreateForItil($item);

        $actions = [
            'PluginSecretTimelineSecret' => [
                'type' => 'PluginSecretTimelineSecret',
                'class' => 'PluginSecretTimelineSecret',
                'icon' => 'ti ti-shield-lock',
                'label' => _n('Secret', 'Secrets', 1, 'secret'),
                'short_label' => _n('Secret', 'Secrets', 1, 'secret'),
                'template' => '@secret/timeline_secret.html.twig',
                'item' => $subitem,
                'hide_in_menu' => true,
            ],
        ];
        if (!$canCreate) {
            return $actions;
        }

        $actions['PluginSecretSecret'] =
            // Stable key and backslash-free class: GLPI uses the class to build
            // the Bootstrap target id `new-PluginSecretSecret-block`.
            [
                'type' => 'ITILFollowup',
                'class' => 'PluginSecretSecret',
                'icon' => 'ti ti-key',
                'label' => __('Add a secret', 'secret'),
                'short_label' => _n('Secret', 'Secrets', 1, 'secret'),
                'template' => '@secret/timeline_form.html.twig',
                'item' => $subitem,
                'hide_in_menu' => false,
            ];
        return $actions;
    }
}
