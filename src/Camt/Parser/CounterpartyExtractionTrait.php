<?php

declare(strict_types=1);

namespace Sepa\Camt\Parser;

use Genkgo\Camt\DTO\Creditor;
use Genkgo\Camt\DTO\Debtor;
use Genkgo\Camt\DTO\EntryTransactionDetail;
use Genkgo\Camt\DTO\IbanAccount;
use Genkgo\Camt\DTO\RelatedParty;
use Genkgo\Camt\DTO\UltimateCreditor;
use Genkgo\Camt\DTO\UltimateDebtor;

/**
 * Shared logic for picking the "counterparty" from a CAMT transaction's
 * related parties, respecting the entry direction.
 *
 * CAMT entries typically carry multiple `RltdPties` children — `Dbtr`,
 * `Cdtr`, `UltmtDbtr`, `UltmtCdtr`. The *counterparty* from the account
 * holder's perspective is always the side *opposite* to the booking
 * direction:
 *
 *  - Credit (incoming): counterparty = Debtor (sender); UltimateDebtor
 *    is a useful secondary hint when the bank routes through an agent
 *    and passes along an internal property/tenant code.
 *  - Debit (outgoing): counterparty = Creditor (receiver); UltimateCreditor
 *    is the corresponding secondary hint.
 *
 * The genkgo/camt reader exposes each `<RltdPties>` child as a
 * `RelatedParty` wrapping a typed `RelatedPartyTypeInterface` (`Debtor`,
 * `Creditor`, etc.), so direction-based selection is a simple instanceof
 * loop.
 *
 * When a typed match isn't available (malformed file, bank quirk, older
 * CAMT variants), this falls back to the first named related party —
 * the pre-0.2 behavior — so callers see *something* rather than null.
 */
trait CounterpartyExtractionTrait
{
    /**
     * @return array{name: string|null, iban: string|null, ultimateName: string|null}
     */
    protected function extractCounterparty(EntryTransactionDetail $detail, bool $isCredit): array
    {
        $primaryTypes = $isCredit ? [Debtor::class] : [Creditor::class];
        $ultimateTypes = $isCredit ? [UltimateDebtor::class] : [UltimateCreditor::class];

        $name = null;
        $iban = null;
        $ultimateName = null;
        $fallbackName = null;
        $fallbackIban = null;

        foreach ($detail->getRelatedParties() as $party) {
            $type = $party->getRelatedPartyType();
            $typeName = $type->getName();

            // Check ultimate types first — genkgo's UltimateDebtor extends
            // Debtor (and UltimateCreditor extends Creditor), so a plain
            // instanceof check for the primary type would also match the
            // ultimate type and never reach this branch.
            if ($this->matchesAny($type, $ultimateTypes)) {
                if ($ultimateName === null && $typeName !== null) {
                    $ultimateName = $typeName;
                }

                continue;
            }

            if ($this->matchesAny($type, $primaryTypes)) {
                if ($name === null && $typeName !== null) {
                    $name = $typeName;
                }
                if ($iban === null) {
                    $iban = $this->ibanOf($party);
                }

                continue;
            }

            // Record the first named "something" we see as a safety net
            // for files that don't use the typed genkgo classes.
            if ($fallbackName === null && $typeName !== null) {
                $fallbackName = $typeName;
            }
            if ($fallbackIban === null) {
                $fallbackIban = $this->ibanOf($party);
            }
        }

        return [
            'name' => $name ?? $fallbackName,
            'iban' => $iban ?? $fallbackIban,
            'ultimateName' => $ultimateName,
        ];
    }

    /**
     * @param object $type genkgo RelatedParty type object (e.g. Debtor, UltimateCreditor).
     * @param list<class-string> $types
     */
    private function matchesAny(object $type, array $types): bool
    {
        foreach ($types as $class) {
            if ($type instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function ibanOf(RelatedParty $party): ?string
    {
        $account = $party->getAccount();
        if ($account instanceof IbanAccount) {
            return $account->getIdentification();
        }

        return null;
    }
}
