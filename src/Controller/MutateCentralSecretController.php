<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Http\Firewall;
use Glpi\Http\RedirectResponse;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Secret\Config;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Service\CentralSecretRepository;
use GlpiPlugin\Secret\Service\LinkedItemContextResolver;
use GlpiPlugin\Secret\Service\SecretLinkService;
use GlpiPlugin\Secret\Service\SecretMutationService;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MutateCentralSecretController extends AbstractController
{
    #[Route('/Central/Secret/{id}', name: 'secret_central_mutate', methods: 'POST', requirements: ['id' => '\d+'])]
    #[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]
    public function __invoke(Request $request, int $id): Response
    {
        Session::checkLoginUser();
        $itemtype = $request->request->getString('itemtype');
        $itemsId = $request->request->getInt('items_id');
        $secret = new Secret();
        if (!$secret->getFromDB($id)) {
            throw new AccessDeniedHttpException();
        }
        if ($request->request->getString('operation') === 'link') {
            $authorized = (new CentralSecretRepository())->authorizedRelation($secret);
            $resolvedTarget = (new LinkedItemContextResolver())->resolve($itemtype, $itemsId);
            if ($authorized === null || $resolvedTarget === null) {
                throw new AccessDeniedHttpException();
            }
            try {
                (new SecretLinkService())->link($secret, $resolvedTarget[0], $resolvedTarget[1]);
                Session::addMessageAfterRedirect(__('Secret linked.', 'secret'));
            } catch (\Throwable) {
                throw new AccessDeniedHttpException();
            }
            return new RedirectResponse(Config::pluginUrl() . '/front/secret.form.php?id=' . $id . '&forcetab=' . rawurlencode(\GlpiPlugin\Secret\SecretRelationTab::class . '$1'));
        }
        $resolved = (new LinkedItemContextResolver())->resolve($itemtype, $itemsId);
        if ($resolved === null || countElementsInTable(SecretItem::getTable(), [
            'plugin_secret_secrets_id' => $id, 'itemtype' => $itemtype, 'items_id' => $itemsId,
        ]) !== 1) {
            throw new AccessDeniedHttpException();
        }
        try {
            $service = new SecretMutationService();
            $deleted = false;
            if ($request->request->getString('operation') === 'delete') {
                $service->delete($secret, $itemtype, $itemsId, $resolved[1], 'central');
                Session::addMessageAfterRedirect(__('Secret deleted.', 'secret'));
                $deleted = true;
            } else {
                $service->update($secret, $request->request->all(), $itemtype, $itemsId, $resolved[1], 'central');
                Session::addMessageAfterRedirect(__('Secret updated.', 'secret'));
            }
        } catch (\Throwable) {
            throw new AccessDeniedHttpException();
        }
        return new RedirectResponse(Config::pluginUrl() . '/front/' . ($deleted ? 'secret.php' : 'secret.form.php?id=' . $id));
    }
}
