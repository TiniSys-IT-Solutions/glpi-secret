<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

/**
 * Safe GLPI item type for Secret cards injected into an ITIL timeline.
 *
 * GLPI also walks timeline items while building ticket notifications and calls
 * getType()/getTypeName() on their declared type. This presentation-only type
 * deliberately has no persistence or plaintext access.
 */
final class TimelineSecret extends \CommonGLPI
{
    /** @param int $nb */
    public static function getTypeName($nb = 0): string
    {
        return _n('Secret', 'Secrets', $nb, 'secret');
    }
}
