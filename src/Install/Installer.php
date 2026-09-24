<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Install;

use GlpiPlugin\Secret\Config;
use GlpiPlugin\Secret\Secret;

final class Installer
{
    public function install(): bool
    {
        global $DB;

        $migration = new \Migration(PLUGIN_SECRET_VERSION);
        foreach (Schema::tables() as $table => $sql) {
            if (!$DB->tableExists($table)) {
                $DB->doQuery($sql);
            }
        }

        // This marker was written by pre-0.1 releases but never participated
        // in authorization or migrations. Remove it while keeping the active
        // one-time bootstrap marker and every encrypted/audit record.
        $DB->delete('glpi_plugin_secret_configs', ['name' => 'profile_rights_defaults_v3']);

        $secretsTable = 'glpi_plugin_secret_secrets';
        if (!$DB->fieldExists($secretsTable, 'plugin_secret_categories_id')) {
            $migration->addField($secretsTable, 'plugin_secret_categories_id', 'int unsigned', [
                'value' => 0,
                'after' => 'groups_id',
            ]);
            $migration->addKey($secretsTable, 'plugin_secret_categories_id', 'category');
        }
        if (!$DB->fieldExists($secretsTable, 'expiration_policy')) {
            $migration->addField($secretsTable, 'expiration_policy', 'varchar(32)', [
                'value' => 'never',
                'after' => 'is_recursive',
            ]);
        }

        $migration->addKey($secretsTable, ['expiration_policy', 'expiration', 'id'], 'closure_expiration');
        if (!(new ProfileRightSynchronizer())->synchronize()) {
            return false;
        }

        if (countElementsInTable('glpi_displaypreferences', [
            'itemtype' => Secret::class,
            'users_id' => 0,
            'interface' => 'central',
        ]) === 0) {
            foreach (Secret::defaultDisplayPreferenceRows() as $preference) {
                if (!$DB->insert('glpi_displaypreferences', $preference)) {
                    return false;
                }
            }
        }

        Config::installDefaults();

        \CronTask::register(Secret::class, 'purgeExpired', DAY_TIMESTAMP, [
            'comment' => __('Purge expired encrypted secrets after the configured retention', 'secret'),
            'mode' => \CronTask::MODE_INTERNAL,
            'param' => 30,
        ]);

        $migration->executeMigration();
        unset($_SESSION['glpimenu']);
        return true;
    }

    public function uninstall(): bool
    {
        // Remove executable scheduling metadata so GLPI never tries to call a
        // class from an uninstalled plugin. Encrypted data, dedicated audit
        // rows, configuration and profile choices remain deliberately intact.
        \CronTask::unregister('Secret');

        // Intentionally retain encrypted records. GLPI's plugin uninstall hook
        // cannot obtain an explicit, informed confirmation before dropping the
        // tables. A separately authorized purge workflow will be added later.
        trigger_error(
            'Secret plugin data was retained to prevent silent loss of encrypted secrets.',
            E_USER_NOTICE,
        );

        return true;
    }
}
