<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use GlpiPlugin\Secret\Config;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\Security\Visibility;

final class SecretInputValidator
{
    public function valueIsValid(#[\SensitiveParameter] string $value): bool
    {
        return $value !== '' && mb_check_encoding($value, 'UTF-8')
            && !str_contains($value, "\0") && strlen($value) <= (int) Config::values()['max_secret_length'];
    }

    /** @param array<string, mixed> $input */
    public function metadataIsValid(array $input): bool
    {
        $name = (string) ($input['name'] ?? '');
        $username = (string) ($input['username'] ?? '');
        return trim($name) !== '' && mb_check_encoding($name . $username, 'UTF-8')
            && mb_strlen($name) <= 255 && mb_strlen($username) <= 255
            && in_array($input['type'] ?? '', Secret::types(), true)
            && in_array($input['visibility'] ?? '', Config::ticketVisibilities(), true)
            && in_array($input['expiration_policy'] ?? ExpirationPolicy::NEVER, ExpirationPolicy::all(), true)
            && (($input['visibility'] ?? '') !== Visibility::GROUP || (int) ($input['groups_id'] ?? 0) > 0);
    }

    public function groupIsValid(int $id, int $entityId): bool
    {
        $group = new \Group();
        if ($id <= 0 || !$group->getFromDB($id) || empty($group->fields['is_assign'])) {
            return false;
        }
        $ownerEntity = (int) $group->fields['entities_id'];
        return $ownerEntity === $entityId || (!empty($group->fields['is_recursive'])
            && in_array($ownerEntity, array_map('intval', getAncestorsOf('glpi_entities', $entityId)), true));
    }
}
