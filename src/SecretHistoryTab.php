<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Secret\Service\CentralSecretRepository;
use GlpiPlugin\Secret\Service\SecretAccessService;

final class SecretHistoryTab extends CommonGLPI
{
    /** @param mixed $withtemplate */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!$item instanceof Secret || !Profile::canViewAudit()) {
            return '';
        }
        return self::createTabEntry(__('History'), 0, $item::getType(), 'ti ti-history');
    }

    /**
     * @param mixed $tabnum
     * @param mixed $withtemplate
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        global $DB;
        if (!$item instanceof Secret) {
            return false;
        }
        $relation = (new CentralSecretRepository())->authorizedRelation($item);
        if ($relation === null || !(new SecretAccessService())->canAudit($item, $relation[2])) {
            return false;
        }
        $logs = SecretLog::getTable();
        $users = \User::getTable();
        $rows = iterator_to_array($DB->request([
            'SELECT' => ["$logs.action", "$logs.users_id", "$logs.date_creation", "$users.name AS user_login"],
            'FROM' => $logs,
            'LEFT JOIN' => [$users => ['FKEY' => [$logs => 'users_id', $users => 'id']]],
            'WHERE' => ["$logs.plugin_secret_secrets_id" => (int) $item->getID()],
            'ORDER' => ["$logs.id DESC"],
            'LIMIT' => 200,
        ]), false);
        TemplateRenderer::getInstance()->display('@secret/secret_history.html.twig', ['entries' => $rows]);
        return true;
    }
}
