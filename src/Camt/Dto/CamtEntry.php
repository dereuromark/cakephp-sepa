<?php

declare(strict_types=1);

namespace Sepa\Camt\Dto;

use Cake\I18n\Date;

/**
 * Normalized view of a single booking entry inside a CAMT statement /
 * notification.
 *
 * Deliberately narrow: only the fields that German Kleinvereine and
 * Freelancer apps actually need for reconciliation are exposed. Consumers
 * who need the full transaction detail can escape to the underlying
 * `genkgo/camt` DTOs via a reference stored elsewhere.
 */
final class CamtEntry
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly bool $isCredit,
        public readonly Date $bookingDate,
        public readonly Date $valueDate,
        public readonly ?string $endToEndId,
        public readonly ?string $remittanceInformation,
        public readonly ?string $counterpartyName,
        public readonly ?string $counterpartyIban,
        public readonly ?string $returnReasonCode = null,
    ) {
    }
}
