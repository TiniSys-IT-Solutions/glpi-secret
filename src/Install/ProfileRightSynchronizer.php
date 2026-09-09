<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Install;

use GlpiPlugin\Secret\Profile as SecretProfile;

final class ProfileRightSynchronizer
{
    private const BOOTSTRAP_MARKER = 'profile_rights_bootstrapped_v1';
    private const DEFAULTS_MARKER = 'profile_rights_defaults_v3';

    public function synchronize(): bool
    {
        global $DB, $GLPI_CACHE;

        $required = array_column(SecretProfile::rights(), 'field');
        $bootstrap = !$this->hasBootstrapMarker();
        $applyDefaults = !$this->hasMarker(self::DEFAULTS_MARKER);
        $success = true;

        foreach ($DB->request(['SELECT' => ['id', 'name', 'interface'], 'FROM' => \Profile::getTable()]) as $profile) {
            $profileId = (int) $profile['id'];
            $defaults = $applyDefaults
                ? $this->bootstrapRights((string) $profile['name'], (string) $profile['interface'])
                : [];
            $existing = \ProfileRight::getProfileRights($profileId, $required);
            foreach (array_diff($required, array_keys($existing)) as $name) {
                $success = $DB->insert(\ProfileRight::getTable(), [
                    'profiles_id' => $profileId,
                    'name' => $name,
                    'rights' => $bootstrap && $this->canConfigureGlpi($profileId)
                        ? ALLSTANDARDRIGHT
                        : ($defaults[$name] ?? 0),
                ]) && $success;
            }

            // One-time upgrade of standard profiles: fill only rights that are
            // still zero, never replace an administrator's non-zero choice.
            foreach ($defaults as $name => $right) {
                if ((int) ($existing[$name] ?? 0) === 0) {
                    $success = $DB->update(\ProfileRight::getTable(), ['rights' => $right], [
                        'profiles_id' => $profileId,
                        'name' => $name,
                        'rights' => 0,
                    ]) && $success;
                }
            }

            if ($bootstrap && $this->canConfigureGlpi($profileId)) {
                $success = $DB->update(\ProfileRight::getTable(), ['rights' => ALLSTANDARDRIGHT], [
                    'profiles_id' => $profileId,
                    'name' => $required,
                ]) && $success;
            }
        }

        // GLPI caches the active profile rights in the session. Refresh the
        // plugin values immediately so a freshly installed or upgraded plugin
        // exposes its timeline action without forcing a logout/login cycle.
        $this->refreshActiveProfileRights();

        if ($bootstrap && $success) {
            $success = $DB->insert('glpi_plugin_secret_configs', [
                'name' => self::BOOTSTRAP_MARKER,
                'value' => '1',
            ]) && $success;
        }
        if ($applyDefaults && $success) {
            $success = $DB->insert('glpi_plugin_secret_configs', [
                'name' => self::DEFAULTS_MARKER,
                'value' => '1',
            ]) && $success;
        }

        $GLPI_CACHE->set('all_possible_rights', []);
        return $success;
    }

    public function refreshActiveProfileRights(): void
    {
        $activeProfile = $_SESSION['glpiactiveprofile'] ?? null;
        if (!is_array($activeProfile)) {
            return;
        }
        $activeProfileId = (int) ($activeProfile['id'] ?? 0);
        if ($activeProfileId <= 0) {
            return;
        }

        $required = array_column(SecretProfile::rights(), 'field');
        $activeRights = \ProfileRight::getProfileRights($activeProfileId, $required);
        foreach ($required as $right) {
            $activeProfile[$right] = (int) ($activeRights[$right] ?? 0);
        }
        $_SESSION['glpiactiveprofile'] = $activeProfile;
    }

    private function hasBootstrapMarker(): bool
    {
        return $this->hasMarker(self::BOOTSTRAP_MARKER);
    }

    private function hasMarker(string $name): bool
    {
        return countElementsInTable('glpi_plugin_secret_configs', ['name' => $name]) > 0;
    }

    private function canConfigureGlpi(int $profileId): bool
    {
        $rights = \ProfileRight::getProfileRights($profileId, ['config']);
        return (((int) ($rights['config'] ?? 0)) & UPDATE) === UPDATE;
    }

    /** @return array<string, int> */
    private function bootstrapRights(string $profileName, string $interface): array
    {
        $readCreateReveal = [
            SecretProfile::RIGHT_METADATA => READ,
            SecretProfile::RIGHT_CREATE => CREATE,
            SecretProfile::RIGHT_REVEAL => READ,
        ];
        if ($interface === 'helpdesk') {
            return $readCreateReveal;
        }
        if (in_array($profileName, ['Hotliner', 'Observer', 'Technician', 'Supervisor'], true)) {
            return $readCreateReveal + [
                SecretProfile::RIGHT_UPDATE => UPDATE,
                SecretProfile::RIGHT_DELETE => DELETE,
            ];
        }
        return [];
    }
}
