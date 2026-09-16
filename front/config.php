<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Secret\Config as SecretConfig;
use GlpiPlugin\Secret\Service\ProfileRightsPresetService;

Session::checkLoginUser();
if (!SecretConfig::canManage()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // GLPI 11's controller listener validates and consumes the CSRF token
    // before loading this legacy plugin file. Checking it a second time would
    // reject every legitimate configuration submission.
    if (isset($_POST['preview_profile_rights']) || isset($_POST['apply_profile_rights'])) {
        $profileRights = new ProfileRightsPresetService();
        try {
            if (isset($_POST['preview_profile_rights'])) {
                SecretConfig::renderForm(SecretConfig::frontUrl(), $profileRights->preview($_POST));
                return;
            }
            $count = $profileRights->apply($_POST);
            Session::addMessageAfterRedirect(sprintf(_n('%d profile updated.', '%d profiles updated.', $count, 'secret'), $count));
        } catch (RuntimeException $exception) {
            Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
        } catch (Throwable) {
            Session::addMessageAfterRedirect(__('The Secret profile rights could not be saved.', 'secret'), false, ERROR);
        }
        Html::redirect(SecretConfig::globalConfigUrl());
    }

    SecretConfig::save($_POST);
    Session::addMessageAfterRedirect(__('Secret settings saved.', 'secret'));
    Html::redirect(SecretConfig::globalConfigUrl());
}

Html::redirect(SecretConfig::globalConfigUrl());
