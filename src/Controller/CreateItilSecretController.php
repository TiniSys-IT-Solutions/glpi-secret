<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use CommonITILObject;
use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\RedirectResponse;
use GlpiPlugin\Secret\Config;
use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Service\CreateSecretService;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateItilSecretController extends AbstractController
{
    #[Route('/Itil/Secret', name: 'secret_itil_create', methods: 'POST')]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        if (!Config::values()['ticket_enabled'] || !Profile::canCreateSecret()) {
            throw new AccessDeniedHttpException();
        }

        $itemtype = $request->request->getString('itemtype');
        $itemsId = $request->request->getInt('items_id');
        if (!in_array($itemtype, SecretItem::supportedItemtypes(), true) || $itemsId <= 0) {
            throw new BadRequestHttpException('Unsupported ITIL object.');
        }
        $item = getItemForItemtype($itemtype);
        if (!$item instanceof CommonITILObject || !$item->getFromDB($itemsId) || !$item->canViewItem()) {
            throw new AccessDeniedHttpException();
        }

        try {
            (new CreateSecretService())->createForItil($item, $request->request->all());
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage());
        } catch (\RuntimeException) {
            throw new BadRequestHttpException(__('The secret could not be created.', 'secret'));
        }

        Session::addMessageAfterRedirect(__('Secret created.', 'secret'));
        return new RedirectResponse($item->getLinkURL());
    }
}
