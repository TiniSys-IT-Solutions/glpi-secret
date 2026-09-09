<?php

declare(strict_types=1);

class CommonGLPI
{
    public function getID(): int {}
    public static function getType(): string {}
    public static function createTabEntry(string $label, int $count = 0, string $type = '', string $icon = ''): string {}
}

class CommonDBTM extends CommonGLPI
{
    public array $fields = [];
    public static function getTable(): string {}
    public function rawSearchOptions(): array {}
    public function getFromDB(int $id): bool {}
    public function add(array $input): int|false {}
    public function update(array $input, bool $history = true): bool {}
    public function delete(array $input, bool $force = false, bool $history = true): bool {}
}

class CommonDBRelation extends CommonDBTM {}

class CommonITILActor
{
    public const REQUESTER = 1;
    public const ASSIGN = 2;
}

class CommonITILObject extends CommonDBTM
{
    public const TIMELINE_RIGHT = 4;
    public function isNewItem(): bool {}
    public function canViewItem(): bool {}
    public function getFromDB(int $id): bool {}
    public function getAllUsers(int $type): array {}
    public function isClosed(): bool {}
    public function getLinkURL(): string {}
}

class Ticket extends CommonITILObject {}
class Change extends CommonITILObject {}
class Problem extends CommonITILObject {}
class User extends CommonDBTM {}

class ITILFollowup extends CommonDBTM {}

class CronTask extends CommonDBTM
{
    public const MODE_INTERNAL = 1;
}

class Profile extends CommonDBTM
{
    public function getFormURL(): string {}
    public function displayRightsChoiceMatrix(array $rights, array $options = []): void {}
}

class ProfileRight extends CommonDBTM
{
    public static function getProfileRights(int $profileId, array $rights = []): array {}
}

class Session
{
    public static function getCurrentInterface(): string|false {}
    public static function checkLoginUser(): void {}
    public static function addMessageAfterRedirect(string $message): void {}
    public static function haveRight(string $right, int $level): bool|int {}
    public static function haveAccessToEntity(int $entityId, bool $recursive = false): bool {}
    public static function getLoginUserID(bool $force = true): int|false {}
}

class Html
{
    public static function hidden(string $name, array $options = []): string {}
    public static function submit(string $label, array $options = []): string {}
    public static function closeForm(): void {}
}

class GLPIKey
{
    public function encrypt(string $value): string {}
    public function decrypt(?string $value): ?string {}
    public function hasReadErrors(): bool {}
}

class Config extends CommonDBTM
{
    public static function getFormURL($full = true): string {}
    public static function getConfigurationValues(string $context): array {}
    public static function setConfigurationValues(string $context, array $values): void {}
}

function __(string $message, string $domain = 'glpi'): string {}
function _n(string $singular, string $plural, int $count, string $domain = 'glpi'): string {}
function _sx(string $context, string $message, string $domain = 'glpi'): string {}
function countElementsInTable(string $table, array $criteria = []): int {}
function getItemForItemtype(string $itemtype): ?CommonDBTM {}

const READ = 1;
const UPDATE = 2;
const CREATE = 4;
const DELETE = 8;
const ALLSTANDARDRIGHT = 31;
