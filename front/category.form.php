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
$category = new Category();
if (isset($_POST['add'])) {
    $category->check(-1, CREATE, $_POST);
    $category->add($_POST);
    Html::back();
}
if (isset($_POST['update'])) {
    $category->check((int) $_POST['id'], UPDATE);
    $category->update($_POST);
    Html::back();
}
if (isset($_POST['purge'])) {
    $category->check((int) $_POST['id'], PURGE);
    $category->delete($_POST, true);
    Html::back();
}
Html::header(Category::getTypeName(2), $_SERVER['PHP_SELF'], 'tools');
$category->showForm((int) ($_GET['id'] ?? 0));
Html::footer();
