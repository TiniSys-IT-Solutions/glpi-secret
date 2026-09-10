<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use CommonITILObject;
use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\SecretLog;
use GlpiPlugin\Secret\Security\ItilActorResolver;
use GlpiPlugin\Secret\Service\SecretAccessService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditSecretController extends AbstractController
{
    #[Route('/Secret/{id}/Audit', name: 'secret_audit', methods: 'POST', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request, int $id): Response
    {
        global $DB;
        Session::checkLoginUser();
        $secret = new Secret();
        $itemtype = $request->request->getString('itemtype');
        $itemsId = $request->request->getInt('items_id');
        if (!$secret->getFromDB($id) || !in_array($itemtype, SecretItem::supportedItemtypes(), true)
            || $itemsId <= 0 || countElementsInTable(SecretItem::getTable(), [
                'plugin_secret_secrets_id' => $id, 'itemtype' => $itemtype, 'items_id' => $itemsId,
            ]) !== 1) {
            throw new AccessDeniedHttpException();
        }
        $item = getItemForItemtype($itemtype);
        if (!$item instanceof CommonITILObject || !$item->getFromDB($itemsId) || !$item->canViewItem()
            || !(new SecretAccessService())->canAudit($secret, (new ItilActorResolver())->forItem($item))) {
            throw new AccessDeniedHttpException();
        }
        $rows = [];
        $logsTable = SecretLog::getTable();
        $usersTable = \User::getTable();
        $before = max(0, $request->request->getInt('before'));
        $where = ["$logsTable.plugin_secret_secrets_id" => $id];
        if ($before > 0) {
            $where["$logsTable.id"] = ['<', $before];
        }
        foreach ($DB->request([
            'SELECT' => ["$logsTable.id", "$logsTable.action", "$logsTable.users_id", "$logsTable.date_creation", "$usersTable.name AS user_login"],
            'FROM' => $logsTable,
            'LEFT JOIN' => [$usersTable => ['FKEY' => [$logsTable => 'users_id', $usersTable => 'id']]],
            'WHERE' => $where,
            'ORDER' => ["$logsTable.id DESC"], 'LIMIT' => 51,
        ]) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'action' => (string) $row['action'],
                'users_id' => (int) $row['users_id'],
                'user_login' => isset($row['user_login']) ? (string) $row['user_login'] : null,
                'date_creation' => (string) $row['date_creation'],
            ];
        }
        $more = count($rows) > 50;
        $rows = array_slice($rows, 0, 50);
        $response = new JsonResponse(['entries' => $rows, 'next_before' => $more ? end($rows)['id'] : null]);
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
