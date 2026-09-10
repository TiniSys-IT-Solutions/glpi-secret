<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

final class SecretLog extends \CommonDBTM
{
    use Security\ClosedGenericAccess;

    /** @var string */
    public static $rightname = Profile::RIGHT_AUDIT;
    /** @var bool */
    public $dohistory = false;

    public function canViewItem(): bool
    {
        return false;
    }
    public function canUpdateItem(): bool
    {
        return false;
    }
    public function canDeleteItem(): bool
    {
        return false;
    }
}
