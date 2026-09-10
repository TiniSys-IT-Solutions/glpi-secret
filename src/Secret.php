<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

use GlpiPlugin\Secret\Security\ClosedGenericAccess;
use GlpiPlugin\Secret\Security\GlpiKeyCipher;
use GlpiPlugin\Secret\Service\SecretAccessService;
use GlpiPlugin\Secret\Service\SecretInputValidator;
use Session;

final class Secret extends \CommonDBTM
{
    use ClosedGenericAccess;
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

    /** @return array<string, string> */
    public static function cronInfo(string $name): array
    {
        return $name === 'purgeExpired' ? [
            'description' => __('Purge expired encrypted secrets after the configured retention', 'secret'),
            'parameter' => __('Retention (days)', 'secret'),
        ] : [];
    }

    public static function cronPurgeExpired(?\CronTask $task = null): int
    {
        return (new Service\ExpiredSecretPurger())->run($task);
    }

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
        if ($plaintext === null || !(new SecretInputValidator())->valueIsValid($plaintext) || !$this->isValidInput($input)) {
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
        if ($hasPlaintext && !(new SecretInputValidator())->valueIsValid($plaintext)) {
            return false;
        }
        if ($hasPlaintext) {
            $input['encrypted_value'] = (new GlpiKeyCipher())->encrypt($plaintext);
        }

        return $input;
    }

    /** @param array<string, mixed> $input */
    private function isValidInput(array $input): bool
    {
        return (new SecretInputValidator())->metadataIsValid($input);
    }
}
