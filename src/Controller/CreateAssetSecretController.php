<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use CommonDBTM;
use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\Firewall;
use Glpi\Http\RedirectResponse;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Secret\Service\AssetTypeProvider;
use GlpiPlugin\Secret\Service\CreateSecretService;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateAssetSecretController extends AbstractController
{
    #[Route('/Asset/Secret', name: 'secret_asset_create', methods: 'POST')]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        $itemtype = $request->request->getString('itemtype');
        $itemsId = $request->request->getInt('items_id');
        if (!(new AssetTypeProvider())->supports($itemtype) || $itemsId <= 0) {
            throw new BadRequestHttpException(__('Unsupported asset.', 'secret'));
        }
        $item = getItemForItemtype($itemtype);
        if (!$item instanceof CommonDBTM || !$item->getFromDB($itemsId) || !$item->canViewItem()) {
            throw new AccessDeniedHttpException();
        }
        try {
            (new CreateSecretService())->createForAsset($item, $request->request->all());
        } catch (\Throwable) {
            throw new BadRequestHttpException(__('The secret could not be created.', 'secret'));
        }
        Session::addMessageAfterRedirect(__('Secret created.', 'secret'));
        return new RedirectResponse($item->getLinkURL());
    }
}
