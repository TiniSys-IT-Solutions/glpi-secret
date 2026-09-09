<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\Security\AclContext;
use RuntimeException;

final class SecretMutationService
{
    public function __construct(
        private readonly SecretAccessService $access = new SecretAccessService(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {}

    /** @param array<string, mixed> $input */
    public function update(
        Secret $secret,
        array $input,
        string $itemtype,
        int $itemsId,
        ?AclContext $context = null,
    ): void {
        global $DB;
        if (!$this->access->canUpdate($secret, $context)) {
            throw new RuntimeException('Access denied.');
        }
        $update = ['id' => (int) $secret->getID()];
        foreach (['name', 'type', 'username'] as $field) {
            if (array_key_exists($field, $input)) {
                $update[$field] = trim((string) $input[$field]);
            }
        }
        if (($input['secret_value'] ?? '') !== '') {
            $update['_secret_value'] = (string) $input['secret_value'];
        }
        $DB->beginTransaction();
        try {
            if (!$secret->update($update, false) || !$this->audit->record((int) $secret->getID(), AuditLogger::UPDATE, [
                'source' => 'itil_tab', 'itemtype' => $itemtype, 'items_id' => $itemsId,
            ])) {
                throw new RuntimeException('Update failed.');
            }
            $DB->commit();
        } catch (\Throwable $e) {
            $DB->rollBack();
            throw $e;
        }
    }

    public function delete(
        Secret $secret,
        string $itemtype,
        int $itemsId,
        ?AclContext $context = null,
    ): void {
        global $DB;
        $id = (int) $secret->getID();
        if (!$this->access->canDelete($secret, $context)) {
            throw new RuntimeException('Access denied.');
        }
        $DB->beginTransaction();
        try {
            if (!$this->audit->record($id, AuditLogger::DELETE, [
                'source' => 'itil_tab', 'itemtype' => $itemtype, 'items_id' => $itemsId,
            ])) {
                throw new RuntimeException('Delete audit failed.');
            }
            if (!$DB->delete(SecretItem::getTable(), ['plugin_secret_secrets_id' => $id])) {
                throw new RuntimeException('Relation delete failed.');
            }
            if (!$secret->delete(['id' => $id], true, false)) {
                throw new RuntimeException('Delete failed.');
            }
            $DB->commit();
        } catch (\Throwable $e) {
            $DB->rollBack();
            throw $e;
        }
    }
}
