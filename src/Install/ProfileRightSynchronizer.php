<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Install;

use GlpiPlugin\Secret\Profile as SecretProfile;

final class ProfileRightSynchronizer
{
    private const BOOTSTRAP_MARKER = 'profile_rights_bootstrapped_v1';

    public function synchronize(): bool
    {
        global $DB, $GLPI_CACHE;

        $required = array_column(SecretProfile::rights(), 'field');
        $bootstrap = !$this->hasBootstrapMarker();
        $success = true;

        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => \Profile::getTable()]) as $profile) {
            $profileId = (int) $profile['id'];
            $existing = \ProfileRight::getProfileRights($profileId, $required);
            foreach (array_diff($required, array_keys($existing)) as $name) {
                $success = $DB->insert(\ProfileRight::getTable(), [
                    'profiles_id' => $profileId,
                    'name' => $name,
                    'rights' => $bootstrap && $this->canConfigureGlpi($profileId) ? ALLSTANDARDRIGHT : 0,
                ]) && $success;
            }

            if ($bootstrap && $this->canConfigureGlpi($profileId)) {
                $success = $DB->update(\ProfileRight::getTable(), ['rights' => ALLSTANDARDRIGHT], [
                    'profiles_id' => $profileId,
                    'name' => $required,
                ]) && $success;
            }
        }

        if ($bootstrap && $success) {
            $success = $DB->insert('glpi_plugin_secret_configs', [
                'name' => self::BOOTSTRAP_MARKER,
                'value' => '1',
            ]) && $success;
        }

        $GLPI_CACHE->set('all_possible_rights', []);
        return $success;
    }

    private function hasBootstrapMarker(): bool
    {
        return countElementsInTable('glpi_plugin_secret_configs', ['name' => self::BOOTSTRAP_MARKER]) > 0;
    }

    private function canConfigureGlpi(int $profileId): bool
    {
        $rights = \ProfileRight::getProfileRights($profileId, ['config']);
        return (((int) ($rights['config'] ?? 0)) & UPDATE) === UPDATE;
    }
}
