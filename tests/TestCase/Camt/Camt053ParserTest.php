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
