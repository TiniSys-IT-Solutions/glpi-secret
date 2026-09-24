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
        string $source = 'itil_tab',
    ): void {
        global $DB;
        if (!$this->access->canUpdate($secret, $context)) {
            throw new RuntimeException('Access denied.');
        }
        $update = ['id' => (int) $secret->getID()];
        foreach (['name', 'type', 'username', 'plugin_secret_categories_id'] as $field) {
            if (array_key_exists($field, $input)) {
                $update[$field] = $field === 'plugin_secret_categories_id'
                    ? (int) $input[$field]
                    : trim((string) $input[$field]);
            }
        }
        if (array_key_exists('plugin_secret_categories_id', $update)) {
            $item = getItemForItemtype($itemtype);
            if (!$item instanceof \CommonDBTM || !$item->getFromDB($itemsId)
                || !(new SecretInputValidator())->categoryIsValid(
                    (int) $update['plugin_secret_categories_id'],
                    (int) ($item->fields['entities_id'] ?? -1),
                )) {
                throw new RuntimeException('Invalid category.');
            }
        }
        $resultingType = (string) ($update['type'] ?? $secret->fields['type'] ?? '');
        if ($resultingType !== Secret::TYPE_CREDENTIAL) {
            $update['username'] = null;
        }
        if (($input['secret_value'] ?? '') !== '') {
            $update['_secret_value'] = (string) $input['secret_value'];
        }
        $DB->beginTransaction();
        try {
            if (!$secret->update($update, false) || !$this->audit->record((int) $secret->getID(), AuditLogger::UPDATE, [
                'source' => $source, 'itemtype' => $itemtype, 'items_id' => $itemsId,
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
        string $source = 'itil_tab',
    ): void {
        global $DB;
        $id = (int) $secret->getID();
        if (!$this->access->canDelete($secret, $context)) {
            throw new RuntimeException('Access denied.');
        }
        $DB->beginTransaction();
        try {
            if (!$this->audit->record($id, AuditLogger::DELETE, [
                'source' => $source, 'itemtype' => $itemtype, 'items_id' => $itemsId,
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
