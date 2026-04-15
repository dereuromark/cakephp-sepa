<?php

declare(strict_types=1);

namespace Sepa\Camt\Dto;

use Cake\I18n\Date;

/**
 * Normalized view of a single booking entry inside a CAMT statement /
 * notification.
 *
 * Deliberately narrow: only the fields that typical DACH small-business
 * reconciliation flows need are exposed. Consumers who need the full
 * transaction detail can escape to the underlying `genkgo/camt` DTOs.
 */
final class CamtEntry
{
    /**
     * @param string $amount Amount as a 2-decimal string (always positive; direction via `$isCredit`).
     * @param string $currency ISO-4217 3-letter currency.
     * @param bool $isCredit True for incoming payments, false for outgoing.
     * @param \Cake\I18n\Date $bookingDate CAMT `<BookgDt>`.
     * @param \Cake\I18n\Date $valueDate CAMT `<ValDt>`.
     * @param string|null $endToEndId CAMT `<EndToEndId>` on the first transaction detail.
     * @param string|null $remittanceInformation CAMT `<RmtInf><Ustrd>` concatenated.
     * @param string|null $counterpartyName CAMT `<Dbtr>` or `<Cdtr>` name depending on direction.
     * @param string|null $counterpartyIban CAMT `<DbtrAcct>` or `<CdtrAcct>` IBAN.
     * @param string|null $returnReasonCode CAMT return reason, if the entry is a bounced/returned payment.
     * @param string|null $ntryRef CAMT `<NtryRef>` — bank-assigned stable id for this entry within the statement. Useful as the canonical dedup key for re-imports; falls back to a fingerprint when absent.
     */
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
        public readonly ?string $ntryRef = null,
    ) {
    }
}
