<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonDBTM;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Security\AssetActorResolver;

final class AssetSecretRepository
{
    public function __construct(
        private readonly SecretAccessService $access = new SecretAccessService(),
        private readonly AssetActorResolver $actors = new AssetActorResolver(),
    ) {}

    public function countVisibleForItem(CommonDBTM $item): int
    {
        global $DB;
        if (!(new AssetTypeProvider())->supports($item->getType()) || !$item->canViewItem()) {
            return 0;
        }
        $query = $this->queryForItem($item);
        $query['COUNT'] = 'total';
        return (int) $DB->request($query)->current()['total'];
    }

    /** @return list<array<string, mixed>> */
    public function visibleMetadataForItem(CommonDBTM $item, int $limit = 50, int $offset = 0): array
    {
        global $DB;
        if (!(new AssetTypeProvider())->supports($item->getType()) || !$item->canViewItem()) {
            return [];
        }
        $table = Secret::getTable();
        $query = $this->queryForItem($item);
        $query['SELECT'] = [
            "$table.id", "$table.name", "$table.type", "$table.username", "$table.visibility",
            "$table.groups_id", "$table.plugin_secret_categories_id", "$table.users_id_creator",
            "$table.entities_id", "$table.is_recursive", "$table.expiration_policy", "$table.expiration",
            "$table.date_creation", "$table.date_mod",
        ];
        $query['ORDER'] = ["$table.date_creation DESC", "$table.id DESC"];
        $query['LIMIT'] = max(1, min(100, $limit));
        $query['START'] = max(0, $offset);
        $context = $this->actors->forItem($item);
        $rows = iterator_to_array($DB->request($query), false);
        $this->access->primeExpirations(array_map(static fn(array $row): int => (int) $row['id'], $rows));
        $result = [];
        foreach ($rows as $row) {
            $secret = new Secret();
            $secret->fields = $row;
            if (!$this->access->canSeeMetadata($secret, $context)) {
                continue;
            }
            $row['id'] = (int) $row['id'];
            $row['form_url'] = Secret::getFormURLWithID((int) $row['id']);
            $row['type_label'] = Secret::typeLabel((string) $row['type']);
            $row['can_reveal'] = $this->access->canReveal($secret, $context);
            $row['can_update'] = $this->access->canUpdate($secret, $context);
            $row['can_delete'] = $this->access->canDelete($secret, $context);
            $row['can_audit'] = $this->access->canAudit($secret, $context);
            $result[] = $row;
        }
        return $result;
    }

    /** @return list<array{id: int, name: string, type_label: string}> */
    public function linkableForItem(CommonDBTM $item, int $limit = 100): array
    {
        global $DB;
        if (!$item->canViewItem()) {
            return [];
        }
        $table = Secret::getTable();
        $links = SecretItem::getTable();
        $entityIds = (new AssetEntityScope())->allowedSecretEntityIds($item);
        if ($entityIds === []) {
            return [];
        }
        $context = $this->actors->forItem($item);
        $linked = [];
        foreach ($DB->request(['SELECT' => ['plugin_secret_secrets_id'], 'FROM' => $links, 'WHERE' => [
            'itemtype' => $item->getType(), 'items_id' => (int) $item->getID(),
        ]]) as $row) {
            $linked[] = (int) $row['plugin_secret_secrets_id'];
        }
        $where = ["$table.entities_id" => $entityIds, $this->access->metadataCriteriaForAsset($context)];
        if ($linked !== []) {
            $where['NOT'] = ["$table.id" => $linked];
        }
        $result = [];
        foreach ($DB->request([
            'SELECT' => [
                "$table.id", "$table.name", "$table.type", "$table.visibility",
                "$table.users_id_creator", "$table.groups_id", "$table.entities_id", "$table.is_recursive",
            ],
            'FROM' => $table,
            'WHERE' => $where,
            'ORDER' => ["$table.name ASC"],
            'LIMIT' => max(1, min(200, $limit)),
        ]) as $row) {
            $secret = new Secret();
            $secret->fields = $row;
            if (!$this->access->canUpdate($secret, $context)) {
                continue;
            }
            $result[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'type_label' => Secret::typeLabel((string) $row['type'])];
        }
        return $result;
    }

    /** @return array<string, mixed> */
    private function queryForItem(CommonDBTM $item): array
    {
        $secrets = Secret::getTable();
        $links = SecretItem::getTable();
        $context = $this->actors->forItem($item);
        return [
            'FROM' => $secrets,
            'JOIN' => [$links => ['FKEY' => [$secrets => 'id', $links => 'plugin_secret_secrets_id']]],
            'WHERE' => [
                "$links.itemtype" => $item->getType(),
                "$links.items_id" => (int) $item->getID(),
                $this->access->metadataCriteriaForAsset($context),
            ],
        ];
    }
}
