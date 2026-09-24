<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;

final class CentralSecretRepository
{
    public function __construct(private readonly SecretAccessService $access = new SecretAccessService()) {}

    /** @return list<int> */
    public function visibleIds(): array
    {
        global $DB;
        if (!\GlpiPlugin\Secret\Profile::canReadMetadata()) {
            return [];
        }
        $table = Secret::getTable();
        $ids = [];
        foreach ($DB->request([
            'SELECT' => [
                "$table.id", "$table.visibility", "$table.groups_id", "$table.users_id_creator",
                "$table.entities_id", "$table.is_recursive", "$table.expiration_policy", "$table.expiration",
            ],
            'FROM' => $table,
            'WHERE' => getEntitiesRestrictCriteria($table, '', '', true),
        ]) as $row) {
            $secret = new Secret();
            $secret->fields = $row;
            if ($this->firstAuthorizedRelation($secret) !== null) {
                $ids[] = (int) $row['id'];
            }
        }
        return $ids;
    }

    /** @return array{0: string, 1: int, 2: \GlpiPlugin\Secret\Security\AclContext}|null */
    public function authorizedRelation(Secret $secret): ?array
    {
        return $this->firstAuthorizedRelation($secret);
    }

    /** @return array{0: string, 1: int, 2: \GlpiPlugin\Secret\Security\AclContext}|null */
    private function firstAuthorizedRelation(Secret $secret): ?array
    {
        global $DB;
        foreach ($DB->request([
            'SELECT' => ['itemtype', 'items_id'],
            'FROM' => SecretItem::getTable(),
            'WHERE' => ['plugin_secret_secrets_id' => (int) $secret->getID()],
        ]) as $link) {
            $resolved = (new LinkedItemContextResolver())->resolve((string) $link['itemtype'], (int) $link['items_id']);
            if ($resolved !== null && $this->access->canSeeMetadata($secret, $resolved[1])) {
                return [(string) $link['itemtype'], (int) $link['items_id'], $resolved[1]];
            }
        }
        return null;
    }
}
