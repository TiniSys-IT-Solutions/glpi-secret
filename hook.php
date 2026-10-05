<?php

declare(strict_types=1);

use GlpiPlugin\Secret\Category;
use GlpiPlugin\Secret\Install\Installer;
use GlpiPlugin\Secret\Profile;

/** @return array<class-string<Category>, string> */
function plugin_secret_getDropdown(): array
{
    return Profile::canAdminister() ? [Category::class => Category::getTypeName(2)] : [];
}

function plugin_secret_install(): bool
{
    return (new Installer())->install();
}

function plugin_secret_uninstall(): bool
{
    return (new Installer())->uninstall();
}
