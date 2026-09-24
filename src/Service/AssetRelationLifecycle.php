<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonDBTM;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use RuntimeException;

final class AssetRelationLifecycle
{
    public static function beforePurge(CommonDBTM $item): void
    {
        global $DB;

        $criteria = ['itemtype' => $item->getType(), 'items_id' => (int) $item->getID()];
        $rows = iterator_to_array($DB->request([
            'SELECT' => ['plugin_secret_secrets_id'],
            'FROM' => SecretItem::getTable(),
            'WHERE' => $criteria,
        ]), false);
        if ($rows === []) {
            return;
        }
        $DB->beginTransaction();
        try {
            foreach ($rows as $row) {
                $secretId = (int) $row['plugin_secret_secrets_id'];
                if (!(new AuditLogger())->record($secretId, AuditLogger::UNLINK, [
                    'source' => 'asset_purge', 'itemtype' => $item->getType(), 'items_id' => (int) $item->getID(),
                ])) {
                    throw new RuntimeException('Asset unlink audit failed.');
                }
            }
            if (!$DB->delete(SecretItem::getTable(), $criteria)) {
                throw new RuntimeException('Asset relations could not be removed.');
            }
            foreach ($rows as $row) {
                $secretId = (int) $row['plugin_secret_secrets_id'];
                if (countElementsInTable(SecretItem::getTable(), ['plugin_secret_secrets_id' => $secretId]) === 0) {
                    $secret = new Secret();
                    if ($secret->getFromDB($secretId) && !$secret->delete(['id' => $secretId], true, false)) {
                        throw new RuntimeException('Orphan secret could not be purged.');
                    }
                }
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
    }
}
