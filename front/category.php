<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Secret\Category;
use GlpiPlugin\Secret\Profile;

Session::checkLoginUser();
if (!Profile::canAdminister()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}
Html::header(Category::getTypeName(2), $_SERVER['PHP_SELF'], 'tools');
echo "<div class='mb-3'><a class='btn btn-primary' href='" . Category::getFormURL() . "'>" . __('Add') . '</a></div>';
Search::show(Category::class);
Html::footer();
