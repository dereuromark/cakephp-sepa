<?php

declare(strict_types=1);

namespace Sepa\Camt\Strategy;

use Sepa\Camt\Dto\CamtEntry;

/**
 * Match a CAMT entry by looking at its `endToEndId` field.
 *
 * Returns the EndToEndId verbatim when present, or `null` when missing.
 * Optionally extracts a substring via a user-supplied regex — useful when
 * the EndToEndId contains both an invoice number and other data (e.g.,
 * `"RE-2026-0001|ACCOUNT-7"`).
 */
class EndToEndIdStrategy implements AutoMatchStrategyInterface
{
    public function __construct(
        protected ?string $extractionPattern = null,
    ) {
    }

    public function match(CamtEntry $entry): ?string
    {
        $id = $entry->endToEndId;
        if ($id === null || $id === '') {
            return null;
        }

        if ($this->extractionPattern === null) {
            return $id;
        }

        if (preg_match($this->extractionPattern, $id, $matches) === 1) {
            return $matches[1] ?? $matches[0];
        }

        return null;
    }
}
