<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Secret\Secret;

Session::checkLoginUser();
Secret::enableCentralUi();
Secret::displayFullPageForItem((int) ($_GET['id'] ?? 0), ['tools', Secret::class]);
