<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\Security\AclContext;
use GlpiPlugin\Secret\Security\GlpiKeyCipher;
use RuntimeException;

final class SecretValueService
{
    public function __construct(
        private readonly SecretAccessService $access = new SecretAccessService(),
        private readonly GlpiKeyCipher $cipher = new GlpiKeyCipher(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {}

    /** @param array<string, mixed> $context */
    public function reveal(Secret $secret, bool $copy = false, ?AclContext $aclContext = null, array $context = []): string
    {
        if (!$this->access->canReveal($secret, $aclContext)) {
            throw new RuntimeException('Access denied.');
        }

        $value = $this->cipher->decrypt((string) ($secret->fields['encrypted_value'] ?? ''));
        if (!$this->audit->record((int) $secret->getID(), $copy ? AuditLogger::COPY : AuditLogger::VIEW, $context)) {
            throw new RuntimeException('The secret access could not be audited.');
        }

        return $value;
    }
}
