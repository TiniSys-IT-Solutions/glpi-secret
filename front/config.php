<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Secret\Config as SecretConfig;

Session::checkLoginUser();
if (!SecretConfig::canManage()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // GLPI 11's controller listener validates and consumes the CSRF token
    // before loading this legacy plugin file. Checking it a second time would
    // reject every legitimate configuration submission.
    SecretConfig::save($_POST);
    Session::addMessageAfterRedirect(__('Secret settings saved.', 'secret'));
    Html::redirect(SecretConfig::globalConfigUrl());
}

Html::redirect(SecretConfig::globalConfigUrl());
