<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Service\LinkedItemContextResolver;
use GlpiPlugin\Secret\Service\SecretValueService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RevealSecretController extends AbstractController
{
    #[Route('/Secret/{id}/Reveal', name: 'secret_reveal', methods: 'POST', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request, int $id): Response
    {
        Session::checkLoginUser();
        $secret = new Secret();
        if ($id <= 0 || !$secret->getFromDB($id)) {
            throw new AccessDeniedHttpException();
        }

        $itemtype = $request->request->getString('itemtype');
        $itemsId = $request->request->getInt('items_id');
        if ($itemsId <= 0) {
            throw new AccessDeniedHttpException();
        }
        if (countElementsInTable(SecretItem::getTable(), [
            'plugin_secret_secrets_id' => $id,
            'itemtype' => $itemtype,
            'items_id' => $itemsId,
        ]) !== 1) {
            throw new AccessDeniedHttpException();
        }
        $resolved = (new LinkedItemContextResolver())->resolve($itemtype, $itemsId);
        if ($resolved === null) {
            throw new AccessDeniedHttpException();
        }
        [, $context] = $resolved;

        $action = $request->request->getString('action', 'view');
        if (!in_array($action, ['view', 'copy'], true)) {
            throw new BadRequestHttpException(__('Unsupported reveal action.', 'secret'));
        }
        try {
            $value = (new SecretValueService())->reveal(
                $secret,
                $action === 'copy',
                $context,
                ['source' => 'linked_item', 'itemtype' => $itemtype, 'items_id' => $itemsId],
            );
        } catch (\RuntimeException) {
            // Do not expose ACL, audit or cryptographic failure details to the
            // caller. Every failure remains closed and returns no plaintext.
            throw new AccessDeniedHttpException();
        }

        $response = new JsonResponse(['value' => $value]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
