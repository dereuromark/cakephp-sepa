<?php

declare(strict_types=1);

namespace Sepa\Camt\Dto;

/**
 * Normalized view of a single CAMT.053 statement (one account, one period).
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
     */
    public function __construct(
        public readonly string $id,
        public readonly string $accountIban,
        public readonly string $currency,
        public readonly array $entries,
    ) {
    }
}
