<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
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
    public function __invoke(int $id): Response
    {
        global $DB;
        Session::checkLoginUser();
        $secret = new Secret();
        if (!$secret->getFromDB($id) || !(new SecretAccessService())->canAudit($secret)) {
            throw new AccessDeniedHttpException();
        }
        $rows = [];
        foreach ($DB->request(['FROM' => SecretLog::getTable(), 'WHERE' => ['plugin_secret_secrets_id' => $id], 'ORDER' => ['date_creation DESC', 'id DESC']]) as $row) {
            $rows[] = ['action' => (string) $row['action'], 'users_id' => (int) $row['users_id'], 'date_creation' => (string) $row['date_creation']];
        }
        $response = new JsonResponse(['entries' => $rows]);
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
