<?php

declare(strict_types=1);

// Isolated doubles for application-service tests; not native GLPI integration.
const READ = 1;
const UPDATE = 2;
const CREATE = 4;
const DELETE = 8;

class CommonGLPI
{
    public static function getType(): string
    {
        return static::class;
    }
}

class CommonDBTM extends CommonGLPI
{
    public array $fields = [];
    public static array $records = [];
    public static array $loads = [];
    public static array $updates = [];

    public function getID(): int
    {
        return (int) $this->fields['id'];
    }

    public static function getTable(): string
    {
        return static::class;
    }

    public function getFromDB(int $id): bool
    {
        self::$loads[] = [static::class, $id];
        $this->fields = self::$records[static::class][$id] ?? [];
        return $this->fields !== [];
    }

    public function canViewItem(): bool
    {
        return !empty($this->fields['viewable']);
    }

    public function update(array $input, bool $history = true): bool
    {
        self::$updates[] = $input;
        return true;
    }

    public function getSpecificMassiveActions($checkitem = null): array
    {
        return [];
    }
}

class CommonDBRelation extends CommonDBTM {}
class CommonTreeDropdown extends CommonDBTM {}
class CommonITILObject extends CommonDBTM {}
class Computer extends CommonDBTM {}
class Profile extends CommonDBTM {}

class Config
{
    public static function getConfigurationValues(string $context): array
    {
        return [];
    }
}

class Session
{
    public static array $rights = [];

    public static function haveRight(string $right, int $mask): int
    {
        return (self::$rights[$right] ?? 0) & $mask;
    }

    public static function getLoginUserID(): int
    {
        return 7;
    }

    public static function haveAccessToEntity(int $id, bool $recursive = false): bool
    {
        return $id === 2;
    }
}

class MassiveAction
{
    public const CLASS_ACTION_SEPARATOR = ':';
}

function __($text, $domain = null): string
{
    return $text;
}

function getItemForItemtype(string $type): ?CommonDBTM
{
    return $type === Computer::class ? new Computer() : null;
}

function getEntitiesRestrictCriteria(string $table, string $field, string $entity, bool $recursive): array
{
    return ["$table.entities_id" => 2];
}

function getAncestorsOf(string $table, int $id): array
{
    return $id === 2 ? [0, 1] : [0];
}
