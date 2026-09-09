<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use CommonITILObject;
use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\Firewall;
use Glpi\Http\RedirectResponse;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Security\ItilActorResolver;
use GlpiPlugin\Secret\Service\SecretMutationService;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MutateItilSecretController extends AbstractController
{
    #[Route('/Secret/{id}/Mutate', name: 'secret_mutate', methods: 'POST', requirements: ['id' => '\\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request, int $id): Response
    {
        Session::checkLoginUser();
        $secret = new Secret();
        $itemtype = $request->request->getString('itemtype');
        $itemsId = $request->request->getInt('items_id');
        if (!$secret->getFromDB($id) || !in_array($itemtype, SecretItem::supportedItemtypes(), true) || $itemsId <= 0
            || countElementsInTable(SecretItem::getTable(), ['plugin_secret_secrets_id' => $id, 'itemtype' => $itemtype, 'items_id' => $itemsId]) !== 1) {
            throw new BadRequestHttpException(__('Unknown secret.', 'secret'));
        }
        $item = getItemForItemtype($itemtype);
        if (!$item instanceof CommonITILObject || !$item->getFromDB($itemsId) || !$item->canViewItem()) {
            throw new AccessDeniedHttpException();
        }
        $service = new SecretMutationService();
        $context = (new ItilActorResolver())->forItem($item);
        try {
            if ($request->request->getString('operation') === 'delete') {
                $service->delete($secret, $itemtype, $itemsId, $context);
                Session::addMessageAfterRedirect(__('Secret deleted.', 'secret'));
            } else {
                $service->update($secret, $request->request->all(), $itemtype, $itemsId, $context);
                Session::addMessageAfterRedirect(__('Secret updated.', 'secret'));
            }
        } catch (\RuntimeException) {
            throw new AccessDeniedHttpException();
        }
        return new RedirectResponse($item->getLinkURL());
    }
}
