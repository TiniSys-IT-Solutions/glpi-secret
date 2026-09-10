<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Security;

/** ITIL services are the only public access path in this milestone. */
trait ClosedGenericAccess
{
    public static function canView(): bool
    {
        return false;
    }
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

    public function canCreateItem(): bool
    {
        return false;
    }
    public function canPurgeItem(): bool
    {
        return false;
    }

    /** @return list<array<string, mixed>> */
    public function rawSearchOptions(): array
    {
        return [];
    }

    /** @return array<string, int> */
    public static function getSystemSQLCriteria(?string $tablename = null): array
    {
        return [($tablename ?? static::getTable()) . '.id' => -1];
    }

    /** Do not retain sensitive form input in GLPI's session retry buffer. */
    protected function saveInput(): void {}
}
