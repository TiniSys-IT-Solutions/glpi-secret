<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Secret\Service\AssetTypeProvider;
use GlpiPlugin\Secret\Service\CentralSecretRepository;
use GlpiPlugin\Secret\Service\LinkedItemContextResolver;
use GlpiPlugin\Secret\Service\SecretAccessService;

final class SecretRelationTab extends CommonGLPI
{
    /** @param mixed $withtemplate */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return $item instanceof Secret ? self::createTabEntry(__('Linked items', 'secret'), 0, $item::getType(), 'ti ti-link') : '';
    }

    /**
     * @param mixed $tabnum
     * @param mixed $withtemplate
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        global $DB;
        if (!$item instanceof Secret || (new CentralSecretRepository())->authorizedRelation($item) === null) {
            return false;
        }
        $rows = [];
        foreach ($DB->request([
            'SELECT' => ['itemtype', 'items_id'],
            'FROM' => SecretItem::getTable(),
            'WHERE' => ['plugin_secret_secrets_id' => (int) $item->getID()],
            'ORDER' => ['itemtype ASC', 'items_id ASC'],
        ]) as $link) {
            $resolved = (new LinkedItemContextResolver())->resolve((string) $link['itemtype'], (int) $link['items_id']);
            if ($resolved === null || !(new SecretAccessService())->canSeeMetadata($item, $resolved[1])) {
                continue;
            }
            $rows[] = [
                'itemtype' => (string) $link['itemtype'],
                'type_name' => $resolved[0]::getTypeName(1),
                'name' => $resolved[0]->getName(),
                'url' => $resolved[0]->getLinkURL(),
            ];
        }
        $canLink = Profile::canUpdateSecret();
        $selector = '';
        if ($canLink) {
            ob_start();
            \Dropdown::showSelectItemFromItemtypes([
                'items_id_name' => 'items_id',
                'itemtype_name' => 'itemtype',
                'itemtypes' => (new AssetTypeProvider())->all(),
                'checkright' => true,
            ]);
            $selector = (string) ob_get_clean();
        }
        TemplateRenderer::getInstance()->display('@secret/secret_relations.html.twig', [
            'relations' => $rows,
            'link_form' => $canLink,
            'link_selector' => $selector,
            'link_action' => Config::pluginUrl() . '/Central/Secret/' . $item->getID(),
        ]);
        return true;
    }
}
