<?php

declare(strict_types=1);

namespace Sepa\Camt\Dto;

use Cake\I18n\Date;

/**
 * Normalized view of a single CAMT.053 statement (one account, one period).
 *
 * `fromDate` / `toDate` come from the optional `<FrToDt>` element and are
 * null when the bank omits it (some Sparkasse / cooperative formats do).
 * They are useful for downstream reconciliation UIs that want to display
 * the statement period next to its imported entries without re-parsing
 * the XML.
 *
 * @phpstan-consistent-constructor
 */
final class CamtStatement
{
    /**
     * @param string $id
     * @param string $accountIban
     * @param string $currency
     * @param list<\Sepa\Camt\Dto\CamtEntry> $entries
     * @param \Cake\I18n\Date|null $fromDate Statement period start (CAMT `<FrToDt><FrDtTm>`).
     * @param \Cake\I18n\Date|null $toDate Statement period end (CAMT `<FrToDt><ToDtTm>`).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $accountIban,
        public readonly string $currency,
        public readonly array $entries,
        public readonly ?Date $fromDate = null,
        public readonly ?Date $toDate = null,
    ) {
    }
}
