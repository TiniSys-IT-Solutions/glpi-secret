<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\Secret;

Session::checkLoginUser();
if (!Profile::canReadMetadata()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

Html::header(_n('Secret', 'Secrets', 2, 'secret'), $_SERVER['PHP_SELF'], 'tools', 'GlpiPlugin\\Secret\\Secret');
Secret::enableCentralUi();
Search::show(Secret::class);
Html::footer();
