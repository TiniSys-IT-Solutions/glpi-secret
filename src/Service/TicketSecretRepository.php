<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonITILObject;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Security\ItilActorResolver;

final class TicketSecretRepository
{
    public function __construct(
        private readonly SecretAccessService $access = new SecretAccessService(),
        private readonly ItilActorResolver $actors = new ItilActorResolver(),
    ) {}

    public function countVisibleForItem(CommonITILObject $item): int
    {
        global $DB;
        if (!in_array($item->getType(), SecretItem::supportedItemtypes(), true) || !$item->canViewItem()) {
            return 0;
        }
        $query = $this->queryForItem($item);
        $query['COUNT'] = 'total';
        $rows = $DB->request($query);
        return (int) $rows->current()['total'];
    }

    /** @return list<array<string, bool|int|string|null>> */
    public function visibleMetadataForItem(CommonITILObject $item, ?int $limit = null, int $offset = 0): array
    {
        global $DB;

        if (!in_array($item->getType(), SecretItem::supportedItemtypes(), true) || !$item->canViewItem()) {
            return [];
        }

        $secretsTable = Secret::getTable();
        $context = $this->actors->forItem($item);
        $result = [];

        $query = $this->queryForItem($item);
        $query['SELECT'] = [
            "$secretsTable.id",
            "$secretsTable.name",
            "$secretsTable.type",
            "$secretsTable.username",
            "$secretsTable.visibility",
            "$secretsTable.groups_id",
            "$secretsTable.users_id_creator",
            "$secretsTable.entities_id",
            "$secretsTable.is_recursive",
            "$secretsTable.expiration_policy",
            "$secretsTable.expiration",
            "$secretsTable.date_creation",
            "$secretsTable.date_mod",
        ];
        $query['ORDER'] = ["$secretsTable.date_creation DESC", "$secretsTable.id DESC"];
        if ($limit !== null) {
            $query['LIMIT'] = max(1, min(100, $limit));
            $query['START'] = max(0, $offset);
        }
        $rows = iterator_to_array($DB->request($query), false);
        foreach (array_chunk(array_map(static fn(array $row): int => (int) $row['id'], $rows), 200) as $ids) {
            $this->access->primeExpirations($ids);
        }

        foreach ($rows as $row) {
            $secret = new Secret();
            $secret->fields = $row;
            if (!$this->access->canSeeMetadata($secret, $context)) {
                continue;
            }
            $result[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'type' => (string) $row['type'],
                'username' => $row['username'] !== null ? (string) $row['username'] : null,
                'visibility' => (string) $row['visibility'],
                'groups_id' => (int) $row['groups_id'],
                'users_id_creator' => (int) $row['users_id_creator'],
                'expiration_policy' => (string) $row['expiration_policy'],
                'expiration' => $row['expiration'] !== null ? (string) $row['expiration'] : null,
                'date_creation' => $row['date_creation'] !== null ? (string) $row['date_creation'] : null,
                'date_mod' => $row['date_mod'] !== null ? (string) $row['date_mod'] : null,
                'can_reveal' => $this->access->canReveal($secret, $context),
                'can_update' => $this->access->canUpdate($secret, $context),
                'can_delete' => $this->access->canDelete($secret, $context),
                'can_audit' => $this->access->canAudit($secret, $context),
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function queryForItem(CommonITILObject $item): array
    {
        $secrets = Secret::getTable();
        $links = SecretItem::getTable();
        return [
            'FROM' => $secrets,
            'JOIN' => [$links => ['FKEY' => [$secrets => 'id', $links => 'plugin_secret_secrets_id']]],
            'WHERE' => [
                "$links.itemtype" => $item->getType(), "$links.items_id" => (int) $item->getID(),
                $this->access->metadataCriteriaForItil($this->actors->forItem($item)),
            ],
        ];
    }
}
