<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use GlpiPlugin\Secret\Install\ProfileRightSynchronizer;
use GlpiPlugin\Secret\Profile as SecretProfile;
use RuntimeException;
use Session;

final class ProfileRightsPresetService
{
    private const PREVIEW_SESSION_KEY = 'plugin_secret_profile_rights_preview';

    public static function canApply(): bool
    {
        return (bool) Session::haveRight('profile', UPDATE);
    }

    /** @return list<array{id: int, name: string}> */
    public function profiles(): array
    {
        global $DB;

        $profiles = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => \Profile::getTable(),
            'ORDER' => ['name ASC'],
        ]) as $profile) {
            $profiles[] = ['id' => (int) $profile['id'], 'name' => (string) $profile['name']];
        }

        return $profiles;
    }

    /** @return array<string, array{label: string, description: string, rights: array<string, int>}> */
    public function presets(): array
    {
        $none = array_fill_keys($this->rightNames(), 0);
        $technician = $none;
        $technician[SecretProfile::RIGHT_METADATA] = READ;
        $technician[SecretProfile::RIGHT_CREATE] = CREATE;
        $technician[SecretProfile::RIGHT_REVEAL] = READ;
        $technician[SecretProfile::RIGHT_UPDATE] = UPDATE;
        $technician[SecretProfile::RIGHT_DELETE] = DELETE;

        $supervisor = $technician;
        $supervisor[SecretProfile::RIGHT_AUDIT] = READ;

        $requester = $none;
        $requester[SecretProfile::RIGHT_METADATA] = READ;
        $requester[SecretProfile::RIGHT_CREATE] = CREATE;
        $requester[SecretProfile::RIGHT_REVEAL] = READ;

        return [
            'technician' => [
                'label' => __('Technician', 'secret'),
                'description' => __('Metadata, creation, reveal, update and deletion.', 'secret'),
                'rights' => $technician,
            ],
            'supervisor' => [
                'label' => __('Supervisor', 'secret'),
                'description' => __('Technician rights plus audit consultation.', 'secret'),
                'rights' => $supervisor,
            ],
            'requester' => [
                'label' => __('Requester', 'secret'),
                'description' => __('Metadata, creation and reveal only.', 'secret'),
                'rights' => $requester,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{token: string, profile_ids: list<int>, preset: string, source_profile_id: int, rows: list<array{id: int, name: string, before: array<string, int>, after: array<string, int>}>}
     */
    public function preview(array $input): array
    {
        if (!self::canApply()) {
            throw new RuntimeException(__('You do not have permission to modify profiles.', 'secret'));
        }

        $selection = $this->selection($input);
        $targetRights = $this->targetRights($selection['preset'], $selection['source_profile_id']);
        $profileNames = array_column($this->profiles(), 'name', 'id');
        $rows = [];

        foreach ($selection['profile_ids'] as $profileId) {
            if (!isset($profileNames[$profileId])) {
                throw new RuntimeException(__('A selected profile no longer exists.', 'secret'));
            }
            $rows[] = [
                'id' => $profileId,
                'name' => $profileNames[$profileId],
                'before' => $this->profileRights($profileId),
                'after' => $targetRights,
            ];
        }

        $token = bin2hex(random_bytes(24));
        $_SESSION[self::PREVIEW_SESSION_KEY] = [
            'token' => $token,
            'expires_at' => time() + 600,
            'profile_ids' => $selection['profile_ids'],
            'preset' => $selection['preset'],
            'source_profile_id' => $selection['source_profile_id'],
            'target_rights' => $targetRights,
            'current_rights' => array_column($rows, 'before', 'id'),
        ];

        return ['token' => $token, 'rows' => $rows] + $selection;
    }

    /** @param array<string, mixed> $input */
    public function apply(array $input): int
    {
        global $DB, $GLPI_CACHE;

        if (!self::canApply()) {
            throw new RuntimeException(__('You do not have permission to modify profiles.', 'secret'));
        }

        $selection = $this->selection($input);
        $preview = $_SESSION[self::PREVIEW_SESSION_KEY] ?? null;
        unset($_SESSION[self::PREVIEW_SESSION_KEY]);
        if (!is_array($preview)
            || !hash_equals((string) ($preview['token'] ?? ''), (string) ($input['preview_token'] ?? ''))
            || (int) ($preview['expires_at'] ?? 0) < time()
            || $preview['profile_ids'] !== $selection['profile_ids']
            || $preview['preset'] !== $selection['preset']
            || $preview['source_profile_id'] !== $selection['source_profile_id']) {
            throw new RuntimeException(__('Preview these profile changes again before applying them.', 'secret'));
        }

        $validIds = array_column($this->profiles(), 'id');
        $rights = $this->targetRights($selection['preset'], $selection['source_profile_id']);
        if (($preview['target_rights'] ?? null) !== $rights) {
            throw new RuntimeException(__('The source profile changed. Preview these rights again.', 'secret'));
        }
        foreach ($selection['profile_ids'] as $profileId) {
            if (($preview['current_rights'][$profileId] ?? null) !== $this->profileRights($profileId)) {
                throw new RuntimeException(__('A target profile changed. Preview these rights again.', 'secret'));
            }
        }
        $DB->beginTransaction();
        try {
            foreach ($selection['profile_ids'] as $profileId) {
                if (!in_array($profileId, $validIds, true)) {
                    throw new RuntimeException(__('A selected profile no longer exists.', 'secret'));
                }
                \ProfileRight::updateProfileRights($profileId, $rights);
                if ($this->profileRights($profileId) !== $rights) {
                    throw new RuntimeException(__('GLPI could not save all Secret profile rights.', 'secret'));
                }
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }

        $GLPI_CACHE->set('all_possible_rights', []);
        (new ProfileRightSynchronizer())->refreshActiveProfileRights();

        return count($selection['profile_ids']);
    }

    /** @return list<array{field: string, label: string}> */
    public function rightLabels(): array
    {
        return array_map(
            static fn(array $right): array => ['field' => $right['field'], 'label' => $right['label']],
            SecretProfile::rights(),
        );
    }

    /** @return list<string> */
    private function rightNames(): array
    {
        return array_column(SecretProfile::rights(), 'field');
    }

    /** @return array<string, int> */
    private function profileRights(int $profileId): array
    {
        $stored = \ProfileRight::getProfileRights($profileId, $this->rightNames());
        $rights = [];
        foreach ($this->rightNames() as $name) {
            $rights[$name] = (int) ($stored[$name] ?? 0);
        }

        return $rights;
    }

    /** @return array<string, int> */
    private function targetRights(string $preset, int $sourceProfileId): array
    {
        if ($preset === 'copy') {
            $validIds = array_column($this->profiles(), 'id');
            if ($sourceProfileId <= 0 || !in_array($sourceProfileId, $validIds, true)) {
                throw new RuntimeException(__('Select a valid source profile.', 'secret'));
            }

            return $this->profileRights($sourceProfileId);
        }

        $presets = $this->presets();
        if (!isset($presets[$preset])) {
            throw new RuntimeException(__('Select a valid rights preset.', 'secret'));
        }

        return $presets[$preset]['rights'];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{profile_ids: list<int>, preset: string, source_profile_id: int}
     */
    private function selection(array $input): array
    {
        $profileIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($input['profile_ids'] ?? null) ? $input['profile_ids'] : []),
            static fn(int $id): bool => $id > 0,
        )));
        sort($profileIds);
        if ($profileIds === []) {
            throw new RuntimeException(__('Select at least one target profile.', 'secret'));
        }

        return [
            'profile_ids' => $profileIds,
            'preset' => (string) ($input['rights_preset'] ?? ''),
            'source_profile_id' => max(0, (int) ($input['source_profile_id'] ?? 0)),
        ];
    }
}
