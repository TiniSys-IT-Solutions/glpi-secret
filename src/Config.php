<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

use GlpiPlugin\Secret\Security\Visibility;

final class Config extends \CommonGLPI
{
    public const CONTEXT = 'plugin:secret';

    /** @param int $nb */
    public static function getTypeName($nb = 0): string
    {
        return __('Secret settings', 'secret');
    }

    public static function getIcon(): string
    {
        return 'ti ti-key';
    }

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            'ticket_enabled' => '1',
            'default_visibility' => Visibility::TICKET_TECHNICIANS,
            'default_expiration' => 'never',
            'generator_length' => '20',
            'generator_lowercase' => '1',
            'generator_uppercase' => '1',
            'generator_digits' => '1',
            'generator_special' => '1',
            'generator_exclude_ambiguous' => '1',
            'max_secret_length' => '65535',
        ];
    }

    public static function installDefaults(): void
    {
        $current = \Config::getConfigurationValues(self::CONTEXT);
        $missing = array_diff_key(self::defaults(), $current);
        if ($missing !== []) {
            \Config::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    /** @return array<string, bool|int|string> */
    public static function values(): array
    {
        $current = \Config::getConfigurationValues(self::CONTEXT);
        $values = array_replace(self::defaults(), $current);

        foreach (['ticket_enabled', 'generator_lowercase', 'generator_uppercase', 'generator_digits', 'generator_special', 'generator_exclude_ambiguous'] as $key) {
            $values[$key] = (bool) (int) $values[$key];
        }
        $values['generator_length'] = max(8, min(256, (int) $values['generator_length']));
        $values['max_secret_length'] = max(1024, min(1048576, (int) $values['max_secret_length']));

        return $values;
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input): void
    {
        $visibility = (string) ($input['default_visibility'] ?? '');
        if (!in_array($visibility, self::ticketVisibilities(), true)) {
            $visibility = Visibility::TICKET_TECHNICIANS;
        }
        $expiration = (string) ($input['default_expiration'] ?? '');
        if (!in_array($expiration, self::expirationPolicies(), true)) {
            $expiration = 'never';
        }

        \Config::setConfigurationValues(self::CONTEXT, [
            'ticket_enabled' => !empty($input['ticket_enabled']) ? '1' : '0',
            'default_visibility' => $visibility,
            'default_expiration' => $expiration,
            'generator_length' => (string) max(8, min(256, (int) ($input['generator_length'] ?? 20))),
            'generator_lowercase' => !empty($input['generator_lowercase']) ? '1' : '0',
            'generator_uppercase' => !empty($input['generator_uppercase']) ? '1' : '0',
            'generator_digits' => !empty($input['generator_digits']) ? '1' : '0',
            'generator_special' => !empty($input['generator_special']) ? '1' : '0',
            'generator_exclude_ambiguous' => !empty($input['generator_exclude_ambiguous']) ? '1' : '0',
            'max_secret_length' => (string) max(1024, min(1048576, (int) ($input['max_secret_length'] ?? 65535))),
        ]);
    }

    /** @return list<string> */
    public static function ticketVisibilities(): array
    {
        return [
            Visibility::OWNER,
            Visibility::TICKET_TECHNICIANS,
            Visibility::REQUESTERS_AND_TECHNICIANS,
            Visibility::GROUP,
        ];
    }

    /** @return list<string> */
    public static function expirationPolicies(): array
    {
        return ['never', 'ticket_closed', 'one_day', 'seven_days', 'thirty_days', 'custom'];
    }
}
