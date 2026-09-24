<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonDBTM;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Security\AclContext;
use RuntimeException;

final class SecretLinkService
{
    public function __construct(
        private readonly SecretAccessService $access = new SecretAccessService(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {}

    public function link(Secret $secret, CommonDBTM $item, AclContext $context): void
    {
        global $DB;
        if (!$this->access->canUpdate($secret, $context) || !(new AssetTypeProvider())->supports($item->getType()) || !$item->canViewItem()) {
            throw new RuntimeException('Access denied.');
        }
        if (!(new AssetEntityScope())->allows($item, (int) ($secret->fields['entities_id'] ?? -1))) {
            throw new RuntimeException('Cross-entity links are not allowed.');
        }
        $criteria = ['plugin_secret_secrets_id' => (int) $secret->getID(), 'itemtype' => $item->getType(), 'items_id' => (int) $item->getID()];
        if (countElementsInTable(SecretItem::getTable(), $criteria) > 0) {
            return;
        }
        $DB->beginTransaction();
        try {
            if (!(new SecretItem())->add($criteria) || !$this->audit->record((int) $secret->getID(), AuditLogger::LINK, [
                'source' => 'asset_tab', 'itemtype' => $item->getType(), 'items_id' => (int) $item->getID(),
            ])) {
                throw new RuntimeException('Link failed.');
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
    }

    public function unlink(Secret $secret, CommonDBTM $item, AclContext $context): void
    {
        global $DB;
        $id = (int) $secret->getID();
        if (!$this->access->canDelete($secret, $context) || !$item->canViewItem()) {
            throw new RuntimeException('Access denied.');
        }
        $criteria = ['plugin_secret_secrets_id' => $id, 'itemtype' => $item->getType(), 'items_id' => (int) $item->getID()];
        if (countElementsInTable(SecretItem::getTable(), $criteria) !== 1) {
            throw new RuntimeException('Unknown relation.');
        }
        $DB->beginTransaction();
        try {
            if (!$this->audit->record($id, AuditLogger::UNLINK, [
                'source' => 'asset_tab', 'itemtype' => $item->getType(), 'items_id' => (int) $item->getID(),
            ]) || !$DB->delete(SecretItem::getTable(), $criteria)) {
                throw new RuntimeException('Unlink failed.');
            }
            if (countElementsInTable(SecretItem::getTable(), ['plugin_secret_secrets_id' => $id]) === 0) {
                if (!$secret->delete(['id' => $id], true, false)) {
                    throw new RuntimeException('Orphan purge failed.');
                }
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
    }
}
