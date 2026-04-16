<?php

declare(strict_types=1);

namespace Sepa\Camt\Normalizer;

use Sepa\Camt\Dto\CamtEntry;

/**
 * Normalize minor quirks across German bank CAMT outputs.
 *
 * This is the 0.1 "thin" implementation: it performs the generic
 * whitespace and case normalization that every downstream matcher benefits
 * from. The intended future shape is a pluggable pipeline with per-bank
 * rules (e.g., "Sparkasse strips trailing plus signs from EndToEndId",
 * "Volksbank pads amounts with leading spaces", etc.) — stubs for those
 * will land in 0.2 once real customer samples are available.
 *
 * Current rules:
 *
 *  - Trim leading/trailing whitespace on free-text fields
 *  - Collapse internal runs of whitespace in `remittanceInformation`
 *  - Upper-case the return reason code (banks vary)
 */
class BankQuirkNormalizer
{
    public function normalize(CamtEntry $entry): CamtEntry
    {
        return new CamtEntry(
            amount: $entry->amount,
            currency: $entry->currency,
            isCredit: $entry->isCredit,
            bookingDate: $entry->bookingDate,
            valueDate: $entry->valueDate,
            endToEndId: $this->trimOrNull($entry->endToEndId),
            remittanceInformation: $this->normalizeRemittance($entry->remittanceInformation),
            counterpartyName: $this->trimOrNull($entry->counterpartyName),
            counterpartyIban: $this->trimOrNull($entry->counterpartyIban),
            returnReasonCode: $entry->returnReasonCode !== null
                ? strtoupper($entry->returnReasonCode)
                : null,
            ntryRef: $entry->ntryRef,
            ultimateCounterpartyName: $this->trimOrNull($entry->ultimateCounterpartyName),
        );
    }

    protected function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function normalizeRemittance(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = preg_replace('/\s+/', ' ', trim($value));

        return $trimmed === '' ? null : $trimmed;
    }
}
