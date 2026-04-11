<?php

declare(strict_types=1);

namespace Sepa\Camt\Strategy;

use Sepa\Camt\Dto\CamtEntry;

/**
 * Contract for strategies that attempt to match a parsed CAMT entry to a
 * local domain object (open invoice, member dues item, etc.).
 *
 * Implementations receive the normalized entry and return a string key that
 * identifies the matched domain object, or `null` if no match was found.
 * The key shape is entirely up to the consumer — the plugin does not own
 * the matching semantics, only the hook for plugging in strategies.
 *
 * Typical strategies:
 *
 *  - `EndToEndIdStrategy`: extracts an invoice/dues-run number from the
 *    `endToEndId` field via a configurable regex
 *  - `RemittanceRegexStrategy`: scans the Verwendungszweck for a pattern
 *  - `CounterpartyNameStrategy`: fuzzy-matches the debtor name against
 *    known customers
 */
interface AutoMatchStrategyInterface
{
    /**
     * Return a match key for the entry, or `null` if no match was found.
     */
    public function match(CamtEntry $entry): ?string;
}
