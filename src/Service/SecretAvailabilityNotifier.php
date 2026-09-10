<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CommonITILObject;

final class SecretAvailabilityNotifier
{
    public function notify(CommonITILObject $item): bool
    {
        $followup = new \ITILFollowup();
        $input = [
            'itemtype' => $item->getType(),
            'items_id' => (int) $item->getID(),
            'content' => $this->messageFor($item),
            'is_private' => 0,
        ];
        return $followup->can(-1, CREATE, $input) && (bool) $followup->add($input);
    }

    private function messageFor(CommonITILObject $item): string
    {
        return match ($item->getType()) {
            \Ticket::class => __('A secure secret is available for this ticket. Sign in to GLPI to view it.', 'secret'),
            \Change::class => __('A secure secret is available for this change. Sign in to GLPI to view it.', 'secret'),
            \Problem::class => __('A secure secret is available for this problem. Sign in to GLPI to view it.', 'secret'),
            default => __('A secure secret is available. Sign in to GLPI to view it.', 'secret'),
        };
    }
}
