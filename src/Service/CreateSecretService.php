<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonDBTM;
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

        $entityId = (int) ($item->fields['entities_id'] ?? -1);
        if (
            !$this->access->canCreateForItil($item)
            || !in_array($item->getType(), SecretItem::supportedItemtypes(), true)
        ) {
            throw new RuntimeException('Access denied.');
        }
        $config = Config::values();
        $plaintext = (string) ($input['secret_value'] ?? '');
        $type = (string) ($input['type'] ?? '');
        $categoryId = (int) ($input['plugin_secret_categories_id'] ?? 0);
        if (!(new SecretInputValidator())->valueIsValid($plaintext)) {
            throw new RuntimeException('The secret value is empty or exceeds the configured limit.');
        }
        if (!(new SecretInputValidator())->categoryIsValid($categoryId, $entityId)) {
            throw new RuntimeException('Invalid category.');
        }
        if (($input['visibility'] ?? $config['default_visibility']) === \GlpiPlugin\Secret\Security\Visibility::GROUP
            && !(new SecretInputValidator())->groupIsValid((int) ($input['groups_id'] ?? 0), $entityId)) {
            throw new RuntimeException('Invalid group.');
        }
        $policy = (string) ($input['expiration_policy'] ?? $config['default_expiration']);

        $DB->beginTransaction();
        try {
            $secret = new Secret();
            $secretId = $secret->add([
                'name' => trim((string) ($input['name'] ?? '')),
                'type' => $type,
                'username' => $type === Secret::TYPE_CREDENTIAL
                    ? (trim((string) ($input['username'] ?? '')) ?: null)
                    : null,
                '_secret_value' => $plaintext,
                'visibility' => (string) ($input['visibility'] ?? $config['default_visibility']),
                'groups_id' => (int) ($input['groups_id'] ?? 0),
                'plugin_secret_categories_id' => $categoryId,
                'entities_id' => $entityId,
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

            if ($policy === ExpirationPolicy::TICKET_CLOSED && $item->isClosed()) {
                ExpirationLifecycle::persist((int) $secretId, date('Y-m-d H:i:s'));
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
        try {
            $notified = (new SecretAvailabilityNotifier())->notify($item);
        } catch (\Throwable) {
            $notified = false;
        }
        if (!$notified) {
            \Session::addMessageAfterRedirect(
                __('Secret saved. No notification followup was added; do not submit it again.', 'secret'),
                false,
                WARNING,
            );
        }
        return $secretId;
    }

    /** @param array<string, mixed> $input */
    public function createForAsset(CommonDBTM $item, array $input): int
    {
        global $DB;

        $entityId = (int) ($item->fields['entities_id'] ?? -1);
        if (!$this->access->canCreateForAsset($item)) {
            throw new RuntimeException('Access denied.');
        }
        $plaintext = (string) ($input['secret_value'] ?? '');
        $type = (string) ($input['type'] ?? '');
        $visibility = (string) ($input['visibility'] ?? \GlpiPlugin\Secret\Security\Visibility::OWNER);
        $categoryId = (int) ($input['plugin_secret_categories_id'] ?? 0);
        if (!(new SecretInputValidator())->valueIsValid($plaintext)
            || !in_array($visibility, [
                \GlpiPlugin\Secret\Security\Visibility::OWNER,
                \GlpiPlugin\Secret\Security\Visibility::GROUP,
                \GlpiPlugin\Secret\Security\Visibility::ASSET_TECHNICAL_PROFILES,
            ], true)
            || !(new SecretInputValidator())->categoryIsValid($categoryId, $entityId)
            || ($visibility === \GlpiPlugin\Secret\Security\Visibility::GROUP
                && !(new SecretInputValidator())->groupIsValid((int) ($input['groups_id'] ?? 0), $entityId))) {
            throw new RuntimeException('Invalid secret input.');
        }
        $policy = (string) ($input['expiration_policy'] ?? ExpirationPolicy::NEVER);
        if ($policy === ExpirationPolicy::TICKET_CLOSED) {
            $policy = ExpirationPolicy::NEVER;
        }

        $DB->beginTransaction();
        try {
            $secret = new Secret();
            $secretId = $secret->add([
                'name' => trim((string) ($input['name'] ?? '')),
                'type' => $type,
                'username' => $type === Secret::TYPE_CREDENTIAL ? (trim((string) ($input['username'] ?? '')) ?: null) : null,
                '_secret_value' => $plaintext,
                'visibility' => $visibility,
                'groups_id' => (int) ($input['groups_id'] ?? 0),
                'plugin_secret_categories_id' => $categoryId,
                'entities_id' => $entityId,
                'is_recursive' => 0,
                'expiration_policy' => $policy,
                'expiration' => $this->expiration->resolve(
                    $policy,
                    isset($input['custom_expiration']) ? (string) $input['custom_expiration'] : null,
                ),
            ]);
            if (!$secretId || !(new SecretItem())->add([
                'plugin_secret_secrets_id' => $secretId,
                'itemtype' => $item->getType(),
                'items_id' => (int) $item->getID(),
            ]) || !$this->audit->record((int) $secretId, AuditLogger::CREATE, [
                'source' => 'asset_tab', 'itemtype' => $item->getType(), 'items_id' => (int) $item->getID(),
            ])) {
                throw new RuntimeException('Asset secret creation failed.');
            }
            $DB->commit();
            return (int) $secretId;
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
    }
}
