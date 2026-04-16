<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase\Camt;

use PHPUnit\Framework\TestCase;
use Sepa\Camt\Exception\CamtParseException;
use Sepa\Camt\Parser\Camt053Parser;

class Camt053ParserTest extends TestCase
{
    protected Camt053Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Camt053Parser();
    }

    public function testParsesSampleFileProducesOneStatement(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $this->assertCount(1, $result->statements);
    }

    public function testStatementHasAccountIban(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $statement = $result->statements[0];
        $this->assertSame('DE89370400440532013000', $statement->accountIban);
    }

    public function testStatementHasAccountBic(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $statement = $result->statements[0];
        $this->assertSame('COBADEFFXXX', $statement->accountBic);
    }

    public function testStatementCarriesEntries(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $statement = $result->statements[0];
        $this->assertCount(1, $statement->entries);
    }

    public function testEntryCarriesAmountAndDirection(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertSame('119.00', $entry->amount);
        $this->assertSame('EUR', $entry->currency);
        $this->assertTrue($entry->isCredit);
    }

    public function testEntryCarriesEndToEndId(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertSame('RE-2026-0001', $entry->endToEndId);
    }

    public function testEntryCarriesRemittanceInformation(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertStringContainsString('RE-2026-0001', $entry->remittanceInformation ?? '');
    }

    public function testEntryCarriesDebtorName(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertSame('Max Mustermann', $entry->counterpartyName);
    }

    /**
     * Direction-aware counterparty selection: for a credit entry, the
     * counterparty is the *debtor* (sender), not the creditor (account
     * holder). Also verifies UltimateDebtor is captured separately.
     *
     * Regression for the pre-0.2 behavior where the parser picked the
     * first-named party, which on real bank files (VR-Bank, Sparkasse)
     * resolved to `<Cdtr>` = the account owner — making every incoming
     * credit look like it was "from yourself".
     */
    public function testCreditEntryPicksDebtorAsCounterpartyAndExposesUltimateDebtor(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02">
  <BkToCstmrStmt>
    <GrpHdr><MsgId>MSG-DIR</MsgId><CreDtTm>2026-03-15T10:00:00</CreDtTm></GrpHdr>
    <Stmt>
      <Id>STMT-DIR</Id>
      <CreDtTm>2026-03-15T10:00:00</CreDtTm>
      <Acct>
        <Id><IBAN>DE36622901100033290008</IBAN></Id>
        <Ccy>EUR</Ccy>
      </Acct>
      <Bal>
        <Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">0.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-01-22</Dt></Dt>
      </Bal>
      <Bal>
        <Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">553.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-01-23</Dt></Dt>
      </Bal>
      <Ntry>
        <Amt Ccy="EUR">553.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Sts>BOOK</Sts>
        <BookgDt><Dt>2026-01-23</Dt></BookgDt>
        <ValDt><Dt>2026-01-23</Dt></ValDt>
        <BkTxCd><Domn><Cd>PMNT</Cd><Fmly><Cd>RCDT</Cd><SubFmlyCd>ESCT</SubFmlyCd></Fmly></Domn></BkTxCd>
        <NtryDtls>
          <TxDtls>
            <Refs><EndToEndId>NOTPROVIDED</EndToEndId></Refs>
            <RltdPties>
              <Dbtr><Nm>Haus- und Grundstücksverwaltung Noack + Werner OHG Dresden</Nm></Dbtr>
              <DbtrAcct><Id><IBAN>DE10850503000221167587</IBAN></Id></DbtrAcct>
              <UltmtDbtr><Nm>Bismarck24/FM53 Scherer</Nm></UltmtDbtr>
              <Cdtr><Nm>Mark Scherer</Nm></Cdtr>
              <CdtrAcct><Id><IBAN>DE36622901100033290008</IBAN></Id></CdtrAcct>
            </RltdPties>
            <RmtInf><Ustrd>mtl. UEberschuss</Ustrd></RmtInf>
          </TxDtls>
        </NtryDtls>
      </Ntry>
    </Stmt>
  </BkToCstmrStmt>
</Document>';

        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];

        $this->assertTrue($entry->isCredit);
        $this->assertSame(
            'Haus- und Grundstücksverwaltung Noack + Werner OHG Dresden',
            $entry->counterpartyName,
            'counterparty should be the <Dbtr> for a credit entry, not the account holder <Cdtr>',
        );
        $this->assertSame('DE10850503000221167587', $entry->counterpartyIban);
        $this->assertSame(
            'Bismarck24/FM53 Scherer',
            $entry->ultimateCounterpartyName,
            '<UltmtDbtr> should land in ultimateCounterpartyName',
        );
    }

    /**
     * Mirror: a debit entry must pick the <Cdtr> (receiver) as its
     * counterparty, and <UltmtCdtr> as ultimate.
     */
    public function testDebitEntryPicksCreditorAsCounterpartyAndExposesUltimateCreditor(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02">
  <BkToCstmrStmt>
    <GrpHdr><MsgId>MSG-DIR-D</MsgId><CreDtTm>2026-03-15T10:00:00</CreDtTm></GrpHdr>
    <Stmt>
      <Id>STMT-DIR-D</Id>
      <CreDtTm>2026-03-15T10:00:00</CreDtTm>
      <Acct>
        <Id><IBAN>DE36622901100033290008</IBAN></Id>
        <Ccy>EUR</Ccy>
      </Acct>
      <Bal>
        <Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">100.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-03-01</Dt></Dt>
      </Bal>
      <Bal>
        <Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">75.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-03-15</Dt></Dt>
      </Bal>
      <Ntry>
        <Amt Ccy="EUR">25.00</Amt>
        <CdtDbtInd>DBIT</CdtDbtInd>
        <Sts>BOOK</Sts>
        <BookgDt><Dt>2026-03-10</Dt></BookgDt>
        <ValDt><Dt>2026-03-10</Dt></ValDt>
        <BkTxCd><Domn><Cd>PMNT</Cd><Fmly><Cd>ICDT</Cd><SubFmlyCd>ESCT</SubFmlyCd></Fmly></Domn></BkTxCd>
        <NtryDtls>
          <TxDtls>
            <Refs><EndToEndId>INVOICE-2026-042</EndToEndId></Refs>
            <RltdPties>
              <Dbtr><Nm>Mark Scherer</Nm></Dbtr>
              <DbtrAcct><Id><IBAN>DE36622901100033290008</IBAN></Id></DbtrAcct>
              <Cdtr><Nm>Stadtwerke Example GmbH</Nm></Cdtr>
              <CdtrAcct><Id><IBAN>DE12500105170648489890</IBAN></Id></CdtrAcct>
              <UltmtCdtr><Nm>Stadtwerke Beneficiary Pool</Nm></UltmtCdtr>
            </RltdPties>
            <RmtInf><Ustrd>Stromrechnung Q1</Ustrd></RmtInf>
          </TxDtls>
        </NtryDtls>
      </Ntry>
    </Stmt>
  </BkToCstmrStmt>
</Document>';

        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];

        $this->assertFalse($entry->isCredit);
        $this->assertSame('Stadtwerke Example GmbH', $entry->counterpartyName);
        $this->assertSame('DE12500105170648489890', $entry->counterpartyIban);
        $this->assertSame('Stadtwerke Beneficiary Pool', $entry->ultimateCounterpartyName);
    }

    public function testParsesFileFromDisk(): void
    {
        $path = ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml';
        $result = $this->parser->parseFile($path);
        $this->assertCount(1, $result->statements);
    }

    public function testInvalidXmlThrowsParserException(): void
    {
        $this->expectException(CamtParseException::class);
        $this->parser->parse('<not-camt/>');
    }

    public function testStatementExposesFromAndToDatesFromFrToDtElement(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $stmt = $result->statements[0];

        $this->assertNotNull($stmt->fromDate, 'fromDate must be populated from <FrToDt><FrDtTm>');
        $this->assertNotNull($stmt->toDate, 'toDate must be populated from <FrToDt><ToDtTm>');
        $this->assertSame('2026-03-01', $stmt->fromDate->format('Y-m-d'));
        $this->assertSame('2026-03-15', $stmt->toDate->format('Y-m-d'));
    }

    public function testStatementFromAndToDatesAreNullWhenFrToDtAbsent(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02">
  <BkToCstmrStmt>
    <GrpHdr><MsgId>MSG-NO-PERIOD</MsgId><CreDtTm>2026-03-15T10:00:00</CreDtTm></GrpHdr>
    <Stmt>
      <Id>STMT-NO-PERIOD</Id>
      <CreDtTm>2026-03-15T10:00:00</CreDtTm>
      <Acct>
        <Id><IBAN>DE89370400440532013000</IBAN></Id>
        <Ccy>EUR</Ccy>
      </Acct>
      <Bal>
        <Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">0.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-03-01</Dt></Dt>
      </Bal>
      <Bal>
        <Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">0.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-03-15</Dt></Dt>
      </Bal>
    </Stmt>
  </BkToCstmrStmt>
</Document>';

        $result = $this->parser->parse($xml);
        $stmt = $result->statements[0];

        $this->assertNull($stmt->fromDate);
        $this->assertNull($stmt->toDate);
    }

    public function testEntryExposesNtryRefWhenPresent(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt053_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];

        $this->assertSame('NTRY-2026-03-10-001', $entry->ntryRef);
    }

    public function testEntryNtryRefIsNullWhenElementAbsent(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02">
  <BkToCstmrStmt>
    <GrpHdr><MsgId>MSG-NO-NTRYREF</MsgId><CreDtTm>2026-03-15T10:00:00</CreDtTm></GrpHdr>
    <Stmt>
      <Id>STMT-NO-NTRYREF</Id>
      <CreDtTm>2026-03-15T10:00:00</CreDtTm>
      <Acct>
        <Id><IBAN>DE89370400440532013000</IBAN></Id>
        <Ccy>EUR</Ccy>
      </Acct>
      <Bal>
        <Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">0.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-03-01</Dt></Dt>
      </Bal>
      <Bal>
        <Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">50.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-03-15</Dt></Dt>
      </Bal>
      <Ntry>
        <Amt Ccy="EUR">50.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Sts>BOOK</Sts>
        <BookgDt><Dt>2026-03-10</Dt></BookgDt>
        <ValDt><Dt>2026-03-10</Dt></ValDt>
        <BkTxCd><Domn><Cd>PMNT</Cd><Fmly><Cd>RCDT</Cd><SubFmlyCd>ESCT</SubFmlyCd></Fmly></Domn></BkTxCd>
      </Ntry>
    </Stmt>
  </BkToCstmrStmt>
</Document>';

        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];

        $this->assertNull($entry->ntryRef);
    }
}
