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
        $links = SecretItem::getTable();
        $ids = [];
        $contexts = [];
        $resolver = new LinkedItemContextResolver();
        foreach ($DB->request([
            'SELECT' => [
                "$table.id", "$table.visibility", "$table.groups_id", "$table.users_id_creator",
                "$table.entities_id", "$table.is_recursive", "$table.expiration_policy", "$table.expiration",
                "$links.itemtype", "$links.items_id",
            ],
            'FROM' => $table,
            'JOIN' => [$links => ['FKEY' => [$table => 'id', $links => 'plugin_secret_secrets_id']]],
            'WHERE' => getEntitiesRestrictCriteria($table, '', '', true),
        ]) as $row) {
            $id = (int) $row['id'];
            if (isset($ids[$id])) {
                continue;
            }
            // Resolve each linked object once for this list computation only.
            // Never reuse authorization facts across requests or profiles.
            $key = (string) $row['itemtype'] . ':' . (int) $row['items_id'];
            if (!array_key_exists($key, $contexts)) {
                $contexts[$key] = $resolver->resolve((string) $row['itemtype'], (int) $row['items_id']);
            }
            $resolved = $contexts[$key];
            $secret = new Secret();
            unset($row['itemtype'], $row['items_id']);
            $secret->fields = $row;
            if ($resolved !== null && $this->access->canSeeMetadata($secret, $resolved[1])) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
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
