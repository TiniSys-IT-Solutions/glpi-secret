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
        return count($this->visibleMetadataForItem($item));
    }

    /** @return list<array<string, bool|int|string|null>> */
    public function visibleMetadataForItem(CommonITILObject $item): array
    {
        global $DB;

        $secretsTable = Secret::getTable();
        $relationsTable = SecretItem::getTable();
        $context = $this->actors->forItem($item);
        $result = [];

        $iterator = $DB->request([
            'SELECT' => [
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
            ],
            'FROM' => $secretsTable,
            'JOIN' => [
                $relationsTable => [
                    'FKEY' => [$secretsTable => 'id', $relationsTable => 'plugin_secret_secrets_id'],
                ],
            ],
            'WHERE' => [
                "$relationsTable.itemtype" => $item->getType(),
                "$relationsTable.items_id" => (int) $item->getID(),
            ],
            'ORDER' => ["$secretsTable.date_creation DESC", "$secretsTable.id DESC"],
        ]);

        foreach ($iterator as $row) {
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
                'can_reveal' => $this->access->canReveal($secret, $context),
                'can_update' => $this->access->canUpdate($secret),
                'can_delete' => $this->access->canDelete($secret),
                'can_audit' => $this->access->canAudit($secret),
            ];
        }

        return $result;
    }
}
