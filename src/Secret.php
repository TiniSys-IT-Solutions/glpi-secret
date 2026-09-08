<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

use GlpiPlugin\Secret\Security\GlpiKeyCipher;
use GlpiPlugin\Secret\Security\Visibility;
use GlpiPlugin\Secret\Service\ExpirationPolicy;
use GlpiPlugin\Secret\Service\SecretAccessService;
use Session;

final class Secret extends \CommonDBTM
{
    /** @var string */
    public static $rightname = Profile::RIGHT_METADATA;
    /** @var list<string> */
    public static $undisclosedFields = ['encrypted_value'];
    /** @var bool */
    public $dohistory = true;
    /** @var list<string> */
    public $history_blacklist = ['encrypted_value'];

    public const TYPE_PASSWORD = 'password';
    public const TYPE_CREDENTIAL = 'credential';
    public const TYPE_TOKEN = 'token';
    public const TYPE_OTHER = 'other';

    /** @param int $nb */
    public static function getTypeName($nb = 0): string
    {
        return _n('Secret', 'Secrets', $nb, 'secret');
    }

    public static function getIcon(): string
    {
        return 'ti ti-key';
    }

    /** @return list<string> */
    public static function types(): array
    {
        return [self::TYPE_PASSWORD, self::TYPE_CREDENTIAL, self::TYPE_TOKEN, self::TYPE_OTHER];
    }

    public static function canCreate(): bool
    {
        return Profile::canCreateSecret();
    }

    public function canViewItem(): bool
    {
        return (new SecretAccessService())->canSeeMetadata($this);
    }

    public function canUpdateItem(): bool
    {
        return (new SecretAccessService())->canUpdate($this);
    }

    public function canDeleteItem(): bool
    {
        return (new SecretAccessService())->canDelete($this);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {
        $plaintext = array_key_exists('_secret_value', $input) ? (string) $input['_secret_value'] : null;
        unset($input['_secret_value'], $input['encrypted_value'], $input['users_id_creator']);
        if ($plaintext === null || !$this->isValidInput($input)) {
            return false;
        }

        $input['encrypted_value'] = (new GlpiKeyCipher())->encrypt($plaintext);
        $input['users_id_creator'] = (int) Session::getLoginUserID();

        return $input;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForUpdate($input)
    {
        $hasPlaintext = array_key_exists('_secret_value', $input);
        $plaintext = $hasPlaintext ? (string) $input['_secret_value'] : '';
        unset($input['_secret_value'], $input['encrypted_value'], $input['users_id_creator']);
        if (!$this->isValidInput(array_merge($this->fields, $input))) {
            return false;
        }
        if ($hasPlaintext) {
            $input['encrypted_value'] = (new GlpiKeyCipher())->encrypt($plaintext);
        }

        return $input;
    }

    /** @return list<array<string, mixed>> */
    public function rawSearchOptions(): array
    {
        $options = parent::rawSearchOptions();
        $options[] = ['id' => '2', 'table' => self::getTable(), 'field' => 'name', 'name' => __('Name')];
        $options[] = ['id' => '3', 'table' => self::getTable(), 'field' => 'type', 'name' => __('Type')];
        $options[] = ['id' => '4', 'table' => self::getTable(), 'field' => 'username', 'name' => __('Username', 'secret')];
        $options[] = ['id' => '5', 'table' => self::getTable(), 'field' => 'expiration', 'name' => __('Expiration')];

        return $options;
    }

    /** @param array<string, mixed> $input */
    private function isValidInput(array $input): bool
    {
        $name = trim((string) ($input['name'] ?? ''));
        $type = (string) ($input['type'] ?? '');
        $visibility = (string) ($input['visibility'] ?? '');
        $groupId = (int) ($input['groups_id'] ?? 0);
        $expirationPolicy = (string) ($input['expiration_policy'] ?? ExpirationPolicy::NEVER);

        return $name !== ''
            && in_array($type, self::types(), true)
            && Visibility::isValid($visibility)
            && in_array($expirationPolicy, ExpirationPolicy::all(), true)
            && ($visibility !== Visibility::GROUP || $groupId > 0);
    }
}
