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
 * Parse CAMT.054 (debit/credit notification) XML into normalized
 * `CamtResult` DTOs.
 *
 * CAMT.054 is typically used for SEPA debit returns (Rückläufer) — the bank
 * notifies the creditor that a previously submitted debit has been returned
 * by the debtor bank. The return reason code is extracted into
 * `CamtEntry::$returnReasonCode` so downstream code can classify the
 * failure (e.g., `AC04` = account closed, `MS03` = no reason).
 */
class Camt054Parser
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
                'Failed to parse CAMT.054 document: ' . $e->getMessage(),
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
                'Failed to parse CAMT.054 file: ' . $e->getMessage(),
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

            $statements[] = new CamtStatement(
                id: $record->getId(),
                accountIban: $iban,
                accountBic: $bic,
                currency: $entries[0]->currency ?? 'EUR',
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

        $detail = $entry->getTransactionDetail();
        $isCredit = true;
        $endToEndId = null;
        $remittance = null;
        $counterpartyName = null;
        $counterpartyIban = null;
        $reasonCode = null;

        if ($detail !== null) {
            $indicator = $detail->getCreditDebitIndicator();
            if ($indicator !== null) {
                $isCredit = $indicator === 'CRDT';
            }
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
                if ($counterpartyName === null && $party->getRelatedPartyType()->getName() !== null) {
                    $counterpartyName = $party->getRelatedPartyType()->getName();
                }
            }
            $returnInfo = $detail->getReturnInformation();
            if ($returnInfo !== null) {
                $reasonCode = $returnInfo->getCode();
            }
        }

        $bookingDate = $entry->getBookingDate();
        $valueDate = $entry->getValueDate();

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
            returnReasonCode: $reasonCode,
        );
    }
}
