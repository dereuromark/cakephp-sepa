<?php

declare(strict_types=1);

namespace Sepa\Camt\Event;

use Sepa\Camt\Dto\CamtEntry;

/**
 * Domain event emitted when a parsed CAMT entry represents a returned
 * (failed) SEPA debit — the bank has reversed a previously submitted debit.
 */
final class DebitReturned
{
    public function __construct(
        public readonly CamtEntry $entry,
        public readonly string $reasonCode,
    ) {
    }
}
