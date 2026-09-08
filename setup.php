<?php

declare(strict_types=1);

use Glpi\Plugin\HookManager;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\SecretLog;
use GlpiPlugin\Secret\Service\TimelineActionProvider;

defined('GLPI_ROOT') or die('No direct access allowed');

const PLUGIN_SECRET_VERSION = '0.0.2';
const PLUGIN_SECRET_MIN_GLPI = '11.0.8';
const PLUGIN_SECRET_MAX_GLPI = '11.1.0';
const PLUGIN_SECRET_MIN_PHP = '8.2.0';

function plugin_secret_autoload(): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
}

function plugin_init_secret(): void
{
    global $PLUGIN_HOOKS;

    plugin_secret_autoload();
    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['secret'] = true;

    $hookManager = new HookManager('secret');
    $hookManager->registerSecureFields([
        'glpi_plugin_secret_secrets.encrypted_value',
    ]);
    $hookManager->registerJavascriptFile('js/secret.js');
    $hookManager->registerCSSFile('css/secret.css');

    if (class_exists(Plugin::class) && Plugin::isPluginActive('secret')) {
        Plugin::registerClass(Profile::class, ['addtabon' => [\Profile::class]]);
        Plugin::registerClass(Secret::class);
        Plugin::registerClass(SecretItem::class, ['addtabon' => SecretItem::supportedItemtypes()]);
        Plugin::registerClass(SecretLog::class);

        if (Profile::canAdminister()) {
            $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['secret'] = 'Config';
        }
        $PLUGIN_HOOKS[Hooks::TIMELINE_ANSWER_ACTIONS]['secret'] = TimelineActionProvider::actions(...);
    }
}

function plugin_version_secret(): array
{
    return [
        'name' => __('Secret', 'secret'),
        'version' => PLUGIN_SECRET_VERSION,
        'author' => 'TiniSys IT Solutions',
        'license' => 'GPL-3.0-or-later',
        'homepage' => 'https://github.com/TiniSys-IT-Solutions/glpi-secret',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_SECRET_MIN_GLPI, 'max' => PLUGIN_SECRET_MAX_GLPI],
            'php' => ['min' => PLUGIN_SECRET_MIN_PHP],
        ],
    ];
}

function plugin_secret_check_prerequisites(): bool
{
    return defined('GLPI_VERSION')
        && version_compare(GLPI_VERSION, PLUGIN_SECRET_MIN_GLPI, '>=')
        && version_compare(GLPI_VERSION, PLUGIN_SECRET_MAX_GLPI, '<')
        && version_compare(PHP_VERSION, PLUGIN_SECRET_MIN_PHP, '>=');
}

function plugin_secret_check_config(bool $verbose = false): bool
{
    return true;
}
