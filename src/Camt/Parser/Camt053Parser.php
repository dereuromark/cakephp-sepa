<?php

declare(strict_types=1);

namespace Sepa\Camt\Parser;

use Cake\I18n\Date;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\DTO\IbanAccount;
use Genkgo\Camt\DTO\Message;
use Genkgo\Camt\Reader;
use Sepa\Camt\Dto\CamtEntry;
use Sepa\Camt\Dto\CamtResult;
use Sepa\Camt\Dto\CamtStatement;
use Sepa\Camt\Exception\CamtParseException;
use Throwable;

/**
 * Parse CAMT.053 (account statement) XML into normalized `CamtResult` DTOs.
 *
 * Wraps `genkgo/camt` to expose a narrow, product-focused surface. Consumers
 * who need the full `genkgo/camt` DTO graph can construct a `Reader`
 * directly; this wrapper is for the 90% case of "I need to match incoming
 * payments to my open invoices."
 */
class Camt053Parser
{
    protected Reader $reader;

    public function __construct(?Reader $reader = null)
    {
        $this->reader = $reader ?? new Reader(Config::getDefault());
    }

    public function parse(string $xml): CamtResult
    {
        try {
            $message = $this->reader->readString($xml);
        } catch (Throwable $e) {
            throw new CamtParseException(
                'Failed to parse CAMT.053 document: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        return $this->buildResult($message);
    }

    public function parseFile(string $path): CamtResult
    {
        try {
            $message = $this->reader->readFile($path);
        } catch (Throwable $e) {
            throw new CamtParseException(
                'Failed to parse CAMT.053 file: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        return $this->buildResult($message);
    }

    protected function buildResult(Message $message): CamtResult
    {
        $statements = [];
        foreach ($message->getRecords() as $record) {
            $iban = '';
            $account = $record->getAccount();
            if ($account instanceof IbanAccount) {
                $iban = $account->getIdentification();
            }

            $entries = [];
            foreach ($record->getEntries() as $entry) {
                $entries[] = $this->mapEntry($entry);
            }

            $statements[] = new CamtStatement(
                id: $record->getId(),
                accountIban: $iban,
                currency: $this->currencyFromEntries($entries),
                entries: $entries,
            );
        }

        return new CamtResult($statements);
    }

    protected function mapEntry(Entry $entry): CamtEntry
    {
        $money = $entry->getAmount();
        $amount = number_format((float)($money->getAmount() / 100), 2, '.', '');
        $currency = $money->getCurrency()->getCode();
        $isCredit = $entry->getBookingDate() !== null
            ? $this->isCreditEntry($entry)
            : true;

        $bookingDate = $entry->getBookingDate();
        $valueDate = $entry->getValueDate();

        $endToEndId = null;
        $remittance = null;
        $counterpartyName = null;
        $counterpartyIban = null;

        $detail = $entry->getTransactionDetail();
        if ($detail !== null) {
            $ref = $detail->getReference();
            if ($ref !== null) {
                $endToEndId = $ref->getEndToEndId();
            }
            $rmt = $detail->getRemittanceInformation();
            if ($rmt !== null) {
                $remittance = $rmt->getMessage();
            }
            foreach ($detail->getRelatedParties() as $party) {
                $partyAccount = $party->getAccount();
                if ($partyAccount instanceof IbanAccount) {
                    $counterpartyIban = $partyAccount->getIdentification();
                }
                $type = $party->getRelatedPartyType();
                if ($counterpartyName === null && $type->getName() !== null) {
                    $counterpartyName = $type->getName();
                }
            }
        }

        return new CamtEntry(
            amount: $amount,
            currency: $currency,
            isCredit: $isCredit,
            bookingDate: Date::parse($bookingDate?->format('Y-m-d') ?? '1970-01-01'),
            valueDate: Date::parse($valueDate?->format('Y-m-d') ?? '1970-01-01'),
            endToEndId: $endToEndId,
            remittanceInformation: $remittance,
            counterpartyName: $counterpartyName,
            counterpartyIban: $counterpartyIban,
        );
    }

    protected function isCreditEntry(Entry $entry): bool
    {
        $detail = $entry->getTransactionDetail();
        if ($detail !== null) {
            $indicator = $detail->getCreditDebitIndicator();
            if ($indicator !== null) {
                return $indicator === 'CRDT';
            }
        }

        // Positive money amount is a credit by genkgo convention
        return $entry->getAmount()->getAmount() >= 0;
    }

    /**
     * @param list<\Sepa\Camt\Dto\CamtEntry> $entries
     */
    protected function currencyFromEntries(array $entries): string
    {
        return $entries[0]->currency ?? 'EUR';
    }
}
