<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretLog;
use GlpiPlugin\Secret\Service\SecretAccessService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditSecretController extends AbstractController
{
    #[Route('/Secret/{id}/Audit', name: 'secret_audit', methods: 'POST', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(int $id): Response
    {
        global $DB;
        Session::checkLoginUser();
        $secret = new Secret();
        if (!$secret->getFromDB($id) || !(new SecretAccessService())->canAudit($secret)) {
            throw new AccessDeniedHttpException();
        }
        $rows = [];
        $logsTable = SecretLog::getTable();
        $usersTable = \User::getTable();
        foreach ($DB->request([
            'SELECT' => ["$logsTable.action", "$logsTable.users_id", "$logsTable.date_creation", "$usersTable.name AS user_login"],
            'FROM' => $logsTable,
            'LEFT JOIN' => [$usersTable => ['FKEY' => [$logsTable => 'users_id', $usersTable => 'id']]],
            'WHERE' => ["$logsTable.plugin_secret_secrets_id" => $id],
            'ORDER' => ["$logsTable.date_creation DESC", "$logsTable.id DESC"],
        ]) as $row) {
            $rows[] = [
                'action' => (string) $row['action'],
                'users_id' => (int) $row['users_id'],
                'user_login' => isset($row['user_login']) ? (string) $row['user_login'] : null,
                'date_creation' => (string) $row['date_creation'],
            ];
        }
        $response = new JsonResponse(['entries' => $rows]);
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
