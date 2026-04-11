<?php

declare(strict_types=1);

namespace Sepa\Camt\Event;

use Sepa\Camt\Dto\CamtEntry;

/**
 * Domain event emitted when a parsed CAMT entry represents an incoming
 * payment (credit to the account holder).
 */
final class PaymentReceived
{
    public function __construct(public readonly CamtEntry $entry)
    {
    }
}
