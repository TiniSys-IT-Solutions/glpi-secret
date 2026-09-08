<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

use CommonGLPI;
use Session;

final class Profile extends \Profile
{
    public const RIGHT_METADATA = 'plugin_secret_metadata';
    public const RIGHT_CREATE = 'plugin_secret_create';
    public const RIGHT_REVEAL = 'plugin_secret_reveal';
    public const RIGHT_UPDATE = 'plugin_secret_update';
    public const RIGHT_DELETE = 'plugin_secret_delete';
    public const RIGHT_AUDIT = 'plugin_secret_audit';
    public const RIGHT_ADMIN = 'plugin_secret_admin';

    public static function canReadMetadata(): bool
    {
        return Session::haveRight(self::RIGHT_METADATA, READ);
    }

    public static function canCreateSecret(): bool
    {
        return Session::haveRight(self::RIGHT_CREATE, CREATE);
    }

    public static function canRevealSecret(): bool
    {
        return Session::haveRight(self::RIGHT_REVEAL, READ);
    }

    public static function canUpdateSecret(): bool
    {
        return Session::haveRight(self::RIGHT_UPDATE, UPDATE);
    }

    public static function canDeleteSecret(): bool
    {
        return Session::haveRight(self::RIGHT_DELETE, DELETE);
    }

    public static function canViewAudit(): bool
    {
        return Session::haveRight(self::RIGHT_AUDIT, READ);
    }

    public static function canAdminister(): bool
    {
        return Session::haveRight(self::RIGHT_ADMIN, UPDATE);
    }

    /** @param bool|int $withtemplate */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!$item instanceof \Profile || $item->getID() <= 0) {
            return '';
        }

        return self::createTabEntry(_n('Secret', 'Secrets', 1, 'secret'), 0, $item::getType(), 'ti ti-key');
    }

    /**
     * @param int $tabnum
     * @param bool|int $withtemplate
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!$item instanceof \Profile) {
            return false;
        }

        $canEdit = Session::haveRight('profile', UPDATE);
        if ($canEdit) {
            echo "<form method='post' action='" . $item->getFormURL() . "'>";
        }
        $item->displayRightsChoiceMatrix(self::rights(), [
            'canedit' => $canEdit,
            'title' => _n('Secret', 'Secrets', 1, 'secret'),
        ]);
        if ($canEdit) {
            echo "<div class='center'>";
            echo \Html::hidden('id', ['value' => $item->getID()]);
            echo \Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo '</div>';
            \Html::closeForm();
        }

        return true;
    }

    /** @return list<array{label: string, field: string, rights: array<int, string>}> */
    public static function rights(): array
    {
        return [
            self::right(__('Read secret metadata', 'secret'), self::RIGHT_METADATA, READ, __('Read')),
            self::right(__('Create secrets', 'secret'), self::RIGHT_CREATE, CREATE, __('Create')),
            self::right(__('Reveal secrets', 'secret'), self::RIGHT_REVEAL, READ, __('Reveal', 'secret')),
            self::right(__('Update secrets', 'secret'), self::RIGHT_UPDATE, UPDATE, __('Update')),
            self::right(__('Delete secrets', 'secret'), self::RIGHT_DELETE, DELETE, __('Delete')),
            self::right(__('View secret audit', 'secret'), self::RIGHT_AUDIT, READ, __('Read')),
            self::right(__('Administer Secret', 'secret'), self::RIGHT_ADMIN, UPDATE, __('Update')),
        ];
    }

    /** @return array{label: string, field: string, rights: array<int, string>} */
    private static function right(string $label, string $field, int $right, string $rightLabel): array
    {
        return ['label' => $label, 'field' => $field, 'rights' => [$right => $rightLabel]];
    }
}
