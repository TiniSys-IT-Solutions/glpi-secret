<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use CommonITILObject;
use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Security\ItilActorResolver;
use GlpiPlugin\Secret\Service\SecretValueService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RevealSecretController extends AbstractController
{
    #[Route('/Secret/{id}/Reveal', name: 'secret_reveal', methods: 'POST', requirements: ['id' => '\\d+'])]
    public function __invoke(Request $request, int $id): Response
    {
        Session::checkLoginUser();
        $secret = new Secret();
        if ($id <= 0 || !$secret->getFromDB($id)) {
            throw new BadRequestHttpException('Unknown secret.');
        }

        $itemtype = $request->request->getString('itemtype');
        $itemsId = $request->request->getInt('items_id');
        if (!in_array($itemtype, SecretItem::supportedItemtypes(), true) || $itemsId <= 0) {
            throw new BadRequestHttpException('Unsupported ITIL object.');
        }
        if (countElementsInTable(SecretItem::getTable(), [
            'plugin_secret_secrets_id' => $id,
            'itemtype' => $itemtype,
            'items_id' => $itemsId,
        ]) !== 1) {
            throw new AccessDeniedHttpException();
        }
        $item = getItemForItemtype($itemtype);
        if (!$item instanceof CommonITILObject || !$item->getFromDB($itemsId) || !$item->canViewItem()) {
            throw new AccessDeniedHttpException();
        }

        $action = $request->request->getString('action', 'view');
        if (!in_array($action, ['view', 'copy'], true)) {
            throw new BadRequestHttpException('Unsupported reveal action.');
        }
        $value = (new SecretValueService())->reveal(
            $secret,
            $action === 'copy',
            (new ItilActorResolver())->forItem($item),
            ['source' => 'itil_tab', 'itemtype' => $itemtype, 'items_id' => $itemsId],
        );

        $response = new JsonResponse(['value' => $value]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
