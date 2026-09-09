<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use GlpiPlugin\Secret\SecretLog;
use Session;

final class AuditLogger
{
    public const CREATE = 'CREATE';
    public const VIEW = 'VIEW';
    public const COPY = 'COPY';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';
    public const PURGE = 'PURGE';

    private const SAFE_CONTEXT_KEYS = ['source', 'itemtype', 'items_id', 'request_id'];

    /** @param array<string, mixed> $context */
    public function record(int $secretId, string $action, array $context = []): bool
    {
        global $DB;

        if ($secretId <= 0 || !in_array($action, self::actions(), true)) {
            return false;
        }

        return $DB->insert(SecretLog::getTable(), [
            'plugin_secret_secrets_id' => $secretId,
            'users_id' => (int) Session::getLoginUserID(),
            'action' => $action,
            'context' => json_encode($this->sanitizeContext($context), JSON_THROW_ON_ERROR),
            'date_creation' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<string> */
    public static function actions(): array
    {
        return [self::CREATE, self::VIEW, self::COPY, self::UPDATE, self::DELETE, self::PURGE];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, bool|float|int|string>
     */
    public function sanitizeContext(array $context): array
    {
        $safe = [];
        foreach (self::SAFE_CONTEXT_KEYS as $key) {
            if (isset($context[$key]) && is_scalar($context[$key])) {
                $safe[$key] = $context[$key];
            }
        }

        return $safe;
    }
}
