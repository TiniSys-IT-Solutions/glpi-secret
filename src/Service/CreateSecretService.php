<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonITILObject;
use GlpiPlugin\Secret\Config;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use RuntimeException;

final class CreateSecretService
{
    public function __construct(
        private readonly SecretAccessService $access = new SecretAccessService(),
        private readonly ExpirationPolicy $expiration = new ExpirationPolicy(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {}

    /** @param array<string, mixed> $input */
    public function createForItil(CommonITILObject $item, array $input): int
    {
        global $DB;

        if (!$this->access->canCreate() || !$item->canViewItem() || !in_array($item->getType(), SecretItem::supportedItemtypes(), true)) {
            throw new RuntimeException('Access denied.');
        }
        $config = Config::values();
        $plaintext = (string) ($input['secret_value'] ?? '');
        if ($plaintext === '' || strlen($plaintext) > (int) $config['max_secret_length']) {
            throw new RuntimeException('The secret value is empty or exceeds the configured limit.');
        }
        $policy = (string) ($input['expiration_policy'] ?? $config['default_expiration']);

        $DB->beginTransaction();
        try {
            $secret = new Secret();
            $secretId = $secret->add([
                'name' => trim((string) ($input['name'] ?? '')),
                'type' => (string) ($input['type'] ?? ''),
                'username' => trim((string) ($input['username'] ?? '')) ?: null,
                '_secret_value' => $plaintext,
                'visibility' => (string) ($input['visibility'] ?? $config['default_visibility']),
                'groups_id' => (int) ($input['groups_id'] ?? 0),
                'entities_id' => (int) ($item->fields['entities_id'] ?? 0),
                'is_recursive' => 0,
                'expiration_policy' => $policy,
                'expiration' => $this->expiration->resolve(
                    $policy,
                    isset($input['custom_expiration']) ? (string) $input['custom_expiration'] : null,
                ),
            ]);
            if (!$secretId) {
                throw new RuntimeException('The secret could not be created.');
            }

            $relation = new SecretItem();
            if (!$relation->add([
                'plugin_secret_secrets_id' => $secretId,
                'itemtype' => $item->getType(),
                'items_id' => (int) $item->getID(),
            ])) {
                throw new RuntimeException('The secret could not be linked to the ITIL object.');
            }
            if (!$this->audit->record($secretId, AuditLogger::CREATE, [
                'source' => 'timeline',
                'itemtype' => $item->getType(),
                'items_id' => (int) $item->getID(),
            ])) {
                throw new RuntimeException('The creation audit could not be recorded.');
            }

            $DB->commit();
            // A native follow-up lets GLPI apply the item's normal notification
            // recipients and templates. Its deliberately generic content never
            // contains secret metadata or a reveal URL.
            try {
                (new SecretAvailabilityNotifier())->notify($item);
            } catch (\Throwable) {
                // The secret is already safely committed. A notification
                // failure must not invite the user to submit it a second time.
            }
            return $secretId;
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
    }
}
