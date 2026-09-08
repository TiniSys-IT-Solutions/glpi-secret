<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Http\RedirectResponse;
use GlpiPlugin\Secret\Config;
use GlpiPlugin\Secret\Profile;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfigController extends AbstractController
{
    #[Route('/Config', name: 'secret_config', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        if (!Profile::canAdminister()) {
            throw new AccessDeniedHttpException();
        }

        if ($request->isMethod('POST')) {
            Config::save($request->request->all());
            Session::addMessageAfterRedirect(__('Secret settings saved.', 'secret'));
            return new RedirectResponse($request->getBasePath() . '/plugins/secret/Config');
        }

        return $this->render('@secret/config.html.twig', ['config' => Config::values()]);
    }
}
