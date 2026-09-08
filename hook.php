<?php

declare(strict_types=1);

use GlpiPlugin\Secret\Install\Installer;

function plugin_secret_install(): bool
{
    return (new Installer())->install();
}

function plugin_secret_uninstall(): bool
{
    return (new Installer())->uninstall();
}
