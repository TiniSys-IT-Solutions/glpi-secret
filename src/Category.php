<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

final class Category extends \CommonTreeDropdown
{
    /** @var string */
    public static $rightname = Profile::RIGHT_ADMIN;
    /** @var bool */
    public $can_be_translated = false;

    /** @param int $nb */
    public static function getTypeName($nb = 0): string
    {
        return _n('Secret category', 'Secret categories', $nb, 'secret');
    }

    public static function getIcon(): string
    {
        return 'ti ti-folders';
    }

    public static function canCreate(): bool
    {
        return Profile::canAdminister();
    }

    public static function canView(): bool
    {
        return Profile::canReadMetadata();
    }

    public static function canUpdate(): bool
    {
        return Profile::canAdminister();
    }

    public static function canDelete(): bool
    {
        return Profile::canAdminister();
    }

    public static function canPurge(): bool
    {
        return Profile::canAdminister();
    }

    public function cleanDBonPurge(): void
    {
        global $DB;
        $DB->update(Secret::getTable(), ['plugin_secret_categories_id' => 0], [
            'plugin_secret_categories_id' => (int) $this->getID(),
        ]);
        parent::cleanDBonPurge();
    }
}
