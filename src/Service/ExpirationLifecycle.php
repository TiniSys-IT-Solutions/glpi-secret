<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonITILObject;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use RuntimeException;

final class ExpirationLifecycle
{
    /** Persist an irreversible expiration when GLPI closes an ITIL object. */
    public static function onUpdate(CommonITILObject $item): void
    {
        if ($item->isClosed()) {
            self::expireForItem($item);
        }
    }

    /** Capture an already closed parent before reopening, including after downtime. */
    public static function beforeUpdate(CommonITILObject $item): void
    {
        self::onUpdate($item);
    }

    /** Parent removal must never reactivate a closure-bound secret. */
    public static function beforePurge(CommonITILObject $item): void
    {
        self::expireForItem($item);
    }

    private static function expireForItem(CommonITILObject $item): void
    {
        global $DB;
        foreach ($DB->request([
            'SELECT' => ['plugin_secret_secrets_id'], 'FROM' => SecretItem::getTable(),
            'WHERE' => ['itemtype' => $item->getType(), 'items_id' => (int) $item->getID()],
        ]) as $row) {
            self::persist((int) $row['plugin_secret_secrets_id'], date('Y-m-d H:i:s'));
        }
    }

    public static function persist(int $id, string $date): void
    {
        global $DB;
        if (!$DB->update(Secret::getTable(), ['expiration' => $date], [
            'id' => $id, 'expiration_policy' => ExpirationPolicy::TICKET_CLOSED, 'expiration' => null,
        ])) {
            throw new RuntimeException('Secret expiration could not be recorded.');
        }
    }

    /**
     * Batch lookup also fails closed for missing parents and orphaned relations.
     * @param list<int>|null $ids
     * @return array<int, string>
     */
    public function pendingExpirations(?array $ids = null, int $limit = 500): array
    {
        global $DB;
        if ($ids === []) {
            return [];
        }
        $secrets = Secret::getTable();
        $links = SecretItem::getTable();
        $where = ["$secrets.expiration_policy" => ExpirationPolicy::TICKET_CLOSED, "$secrets.expiration" => null];
        if ($ids !== null) {
            $where["$secrets.id"] = $ids;
        }
        $result = [];
        foreach ([...SecretItem::supportedItemtypes(), null] as $type) {
            if ($ids === null && count($result) >= $limit) {
                break;
            }
            $query = [
                'SELECT' => ["$secrets.id", "$secrets.date_creation"],
                'FROM' => $secrets,
                'LEFT JOIN' => [$links => ['FKEY' => [$secrets => 'id', $links => 'plugin_secret_secrets_id']]],
                'WHERE' => $where,
                'ORDER' => ["$secrets.id ASC"], 'LIMIT' => $limit - count($result),
            ];
            if ($ids !== null) {
                unset($query['LIMIT']);
            }
            if ($type === null) {
                $query['WHERE']["$links.id"] = null;
            } else {
                $parent = $type::getTable();
                $query['SELECT'][] = "$parent.closedate";
                $query['LEFT JOIN'][$parent] = ['FKEY' => [$links => 'items_id', $parent => 'id']];
                $query['WHERE']["$links.itemtype"] = $type;
                $query['WHERE']['OR'] = ["$parent.id" => null, "$parent.status" => $type::getClosedStatusArray()];
            }
            foreach ($DB->request($query) as $row) {
                $date = (string) ($row['closedate'] ?? date('Y-m-d H:i:s'));
                $date = max($date, (string) ($row['date_creation'] ?? $date));
                $id = (int) $row['id'];
                $result[$id] = isset($result[$id]) ? min($date, $result[$id]) : $date;
            }
        }
        return $result;
    }

    public function synchronize(): void
    {
        foreach ($this->pendingExpirations() as $id => $date) {
            self::persist($id, $date);
        }
    }
}
