<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Http\Firewall;
use Glpi\Http\RedirectResponse;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Service\AssetTypeProvider;
use GlpiPlugin\Secret\Service\LinkedItemContextResolver;
use GlpiPlugin\Secret\Service\SecretLinkService;
use GlpiPlugin\Secret\Service\SecretMutationService;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MutateAssetSecretController extends AbstractController
{
    #[Route('/Asset/Secret/{id}', name: 'secret_asset_mutate', methods: 'POST', requirements: ['id' => '\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request, int $id): Response
    {
        Session::checkLoginUser();
        $itemtype = $request->request->getString('itemtype');
        $itemsId = $request->request->getInt('items_id');
        if (!(new AssetTypeProvider())->supports($itemtype)) {
            throw new BadRequestHttpException(__('Unsupported asset.', 'secret'));
        }
        $resolved = (new LinkedItemContextResolver())->resolve($itemtype, $itemsId);
        $secret = new Secret();
        if ($resolved === null || !$secret->getFromDB($id)) {
            throw new AccessDeniedHttpException();
        }
        [$item, $context] = $resolved;
        try {
            $operation = $request->request->getString('operation');
            if ($operation === 'link') {
                (new SecretLinkService())->link($secret, $item, $context);
                Session::addMessageAfterRedirect(__('Secret linked.', 'secret'));
            } elseif ($operation === 'unlink') {
                (new SecretLinkService())->unlink($secret, $item, $context);
                Session::addMessageAfterRedirect(__('Secret unlinked.', 'secret'));
            } else {
                if (countElementsInTable(SecretItem::getTable(), [
                    'plugin_secret_secrets_id' => $id,
                    'itemtype' => $itemtype,
                    'items_id' => $itemsId,
                ]) !== 1) {
                    throw new \RuntimeException('Unknown relation.');
                }
                (new SecretMutationService())->update($secret, $request->request->all(), $itemtype, $itemsId, $context, 'asset_tab');
                Session::addMessageAfterRedirect(__('Secret updated.', 'secret'));
            }
        } catch (\Throwable) {
            throw new AccessDeniedHttpException();
        }
        return new RedirectResponse($item->getLinkURL());
    }
}
