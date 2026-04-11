<?php

declare(strict_types=1);

namespace Sepa\Camt\Dto;

/**
 * Top-level parse result: one or more `CamtStatement` objects. A single
 * CAMT.053 file typically carries one statement but may carry multiple if
 * the bank batches several accounts together.
 */
final class CamtResult
{
    /**
     * @param list<\Sepa\Camt\Dto\CamtStatement> $statements
     */
    public function __construct(public readonly array $statements)
    {
    }
}
