<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

use CommonGLPI;
use CommonITILObject;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Secret\Service\SecretAccessService;
use GlpiPlugin\Secret\Service\TicketSecretRepository;
use Session;

final class SecretItem extends \CommonDBRelation
{
    use Security\ClosedGenericAccess;
    /** @var string */
    public static $rightname = Profile::RIGHT_METADATA;
    /** @var class-string<Secret> */
    public static $itemtype_1 = Secret::class;
    /** @var string */
    public static $items_id_1 = 'plugin_secret_secrets_id';
    /** @var bool */
    public static $take_entity_1 = false;
    /** @var string */
    public static $itemtype_2 = 'itemtype';
    /** @var string */
    public static $items_id_2 = 'items_id';
    /** @var bool */
    public static $take_entity_2 = true;

    /** @return list<class-string<CommonITILObject>> */
    public static function supportedItemtypes(): array
    {
        return [\Ticket::class, \Change::class, \Problem::class];
    }

    public static function getIcon(): string
    {
        return 'ti ti-key';
    }

    /** @param bool|int $withtemplate */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (
            $withtemplate
            || !$item instanceof CommonITILObject
            || Session::getCurrentInterface() === 'helpdesk'
            || !in_array($item->getType(), self::supportedItemtypes(), true)
            || (!Profile::canReadMetadata() && !Profile::canCreateSecret())
        ) {
            return '';
        }

        $count = !empty($_SESSION['glpishow_count_on_tabs']) && Profile::canReadMetadata()
            ? (new TicketSecretRepository())->countVisibleForItem($item)
            : 0;

        return self::createTabEntry(
            _n('Secret', 'Secrets', 2, 'secret'),
            $count,
            $item::getType(),
            self::getIcon(),
        );
    }

    /**
     * @param int $tabnum
     * @param bool|int $withtemplate
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!$item instanceof CommonITILObject || !$item->canViewItem()) {
            return false;
        }

        $page = max(0, (int) ($_GET['secret_page'] ?? 0));
        $repository = new TicketSecretRepository();
        $total = $repository->countVisibleForItem($item);
        $page = min($page, max(0, (int) ceil($total / 50) - 1));
        TemplateRenderer::getInstance()->display('@secret/itil_tab.html.twig', [
            'page' => $page, 'total' => $total,
            'page_url' => $item->getLinkURL() . '&forcetab=' . rawurlencode(self::getType() . '$1') . '&secret_page=',
            'item' => $item,
            'secrets' => Profile::canReadMetadata()
                ? $repository->visibleMetadataForItem($item, 50, $page * 50)
                : [],
            'can_create' => (new SecretAccessService())->canCreateForItil($item),
            'generator' => Config::values(),
        ]);

        return true;
    }
}
