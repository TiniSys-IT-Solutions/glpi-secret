<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

final class SecretLog extends \CommonDBTM
{
    /** @var string */
    public static $rightname = Profile::RIGHT_AUDIT;
    /** @var bool */
    public $dohistory = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return false;
    }

    public static function canDelete(): bool
    {
        return false;
    }

    public static function canPurge(): bool
    {
        return false;
    }
}
