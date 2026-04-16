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
 * Parse CAMT.052 (interim account report / intraday statement) XML into
 * normalized `CamtResult` DTOs.
 *
 * CAMT.052 is the "BankToCustomerAccountReport" message — banks emit it as
 * an intraday / on-demand report, in contrast to CAMT.053 which is the
 * end-of-day statement. Content-wise both expose the same entry surface via
 * `genkgo/camt`, so the normalization produced here is interchangeable with
 * what `Camt053Parser` returns; callers can feed the resulting `CamtResult`
 * through the same matching + reconciliation pipeline.
 *
 * Supports camt.052.001.01 through .08 — the underlying `genkgo/camt`
 * `Reader` auto-detects the schema version from the document namespace.
 */
class Camt052Parser
{
    use CounterpartyExtractionTrait;

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
                'Failed to parse CAMT.052 document: ' . $e->getMessage(),
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
                'Failed to parse CAMT.052 file: ' . $e->getMessage(),
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
            $bic = $record->getAccountServicerBic();
            $account = $record->getAccount();
            if ($account instanceof IbanAccount) {
                $iban = $account->getIdentification();
            }

            $entries = [];
            foreach ($record->getEntries() as $entry) {
                $entries[] = $this->mapEntry($entry);
            }

            $fromRaw = $record->getFromDate();
            $toRaw = $record->getToDate();

            $statements[] = new CamtStatement(
                id: $record->getId(),
                accountIban: $iban,
                accountBic: $bic,
                currency: $this->currencyFromEntries($entries),
                entries: $entries,
                fromDate: $fromRaw !== null ? Date::parse($fromRaw->format('Y-m-d')) : null,
                toDate: $toRaw !== null ? Date::parse($toRaw->format('Y-m-d')) : null,
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
        $ultimateCounterpartyName = null;

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
            $party = $this->extractCounterparty($detail, $isCredit);
            $counterpartyName = $party['name'];
            $counterpartyIban = $party['iban'];
            $ultimateCounterpartyName = $party['ultimateName'];
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
            ntryRef: $entry->getReference(),
            ultimateCounterpartyName: $ultimateCounterpartyName,
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
