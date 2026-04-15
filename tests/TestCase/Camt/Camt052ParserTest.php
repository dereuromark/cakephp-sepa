<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase\Camt;

use PHPUnit\Framework\TestCase;
use Sepa\Camt\Exception\CamtParseException;
use Sepa\Camt\Parser\Camt052Parser;

class Camt052ParserTest extends TestCase
{
    protected Camt052Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Camt052Parser();
    }

    public function testParsesSampleFileProducesOneStatement(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $this->assertCount(1, $result->statements);
    }

    public function testStatementHasAccountIban(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $statement = $result->statements[0];
        $this->assertSame('DE89370400440532013000', $statement->accountIban);
    }

    public function testStatementHasAccountBic(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $statement = $result->statements[0];
        $this->assertSame('COBADEFFXXX', $statement->accountBic);
    }

    public function testStatementCarriesEntries(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $statement = $result->statements[0];
        $this->assertCount(1, $statement->entries);
    }

    public function testEntryCarriesAmountAndDirection(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertSame('119.00', $entry->amount);
        $this->assertSame('EUR', $entry->currency);
        $this->assertTrue($entry->isCredit);
    }

    public function testEntryCarriesEndToEndId(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertSame('RE-2026-0052', $entry->endToEndId);
    }

    public function testEntryCarriesRemittanceInformation(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertStringContainsString('RE-2026-0052', $entry->remittanceInformation ?? '');
    }

    public function testEntryCarriesDebtorName(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertSame('Erika Musterfrau', $entry->counterpartyName);
    }

    public function testParsesFileFromDisk(): void
    {
        $path = ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml';
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
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $stmt = $result->statements[0];

        $this->assertNotNull($stmt->fromDate, 'fromDate must be populated from <FrToDt><FrDtTm>');
        $this->assertNotNull($stmt->toDate, 'toDate must be populated from <FrToDt><ToDtTm>');
        $this->assertSame('2026-03-01', $stmt->fromDate->format('Y-m-d'));
        $this->assertSame('2026-03-15', $stmt->toDate->format('Y-m-d'));
    }

    public function testEntryExposesNtryRefWhenPresent(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt052_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];

        $this->assertSame('NTRY-RPT-2026-03-10-001', $entry->ntryRef);
    }

    /**
     * CAMT.052 V08 is what current German banks (Sparkassen, VR) emit for
     * intraday reports. The `Reader` auto-detects the schema version from
     * the document namespace and dispatches to the matching decoder, so
     * this parser must transparently handle V08 payloads too.
     */
    public function testParsesCamt052V08Document(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.052.001.08">
  <BkToCstmrAcctRpt>
    <GrpHdr><MsgId>MSG-V08</MsgId><CreDtTm>2026-03-15T10:00:00</CreDtTm></GrpHdr>
    <Rpt>
      <Id>RPT-V08</Id>
      <CreDtTm>2026-03-15T10:00:00</CreDtTm>
      <Acct>
        <Id><IBAN>DE89370400440532013000</IBAN></Id>
        <Ccy>EUR</Ccy>
      </Acct>
      <Bal>
        <Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">500.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-03-01</Dt></Dt>
      </Bal>
      <Bal>
        <Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">542.50</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2026-03-15</Dt></Dt>
      </Bal>
      <Ntry>
        <Amt Ccy="EUR">42.50</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Sts><Cd>BOOK</Cd></Sts>
        <BookgDt><Dt>2026-03-10</Dt></BookgDt>
        <ValDt><Dt>2026-03-10</Dt></ValDt>
        <BkTxCd><Domn><Cd>PMNT</Cd><Fmly><Cd>RCDT</Cd><SubFmlyCd>ESCT</SubFmlyCd></Fmly></Domn></BkTxCd>
        <NtryDtls>
          <TxDtls>
            <Refs><EndToEndId>V08-001</EndToEndId></Refs>
            <RltdPties>
              <Dbtr><Pty><Nm>V08 Debtor</Nm></Pty></Dbtr>
            </RltdPties>
            <RmtInf><Ustrd>V08 remittance</Ustrd></RmtInf>
          </TxDtls>
        </NtryDtls>
      </Ntry>
    </Rpt>
  </BkToCstmrAcctRpt>
</Document>';

        $result = $this->parser->parse($xml);
        $this->assertCount(1, $result->statements);

        $entry = $result->statements[0]->entries[0];
        $this->assertSame('42.50', $entry->amount);
        $this->assertSame('EUR', $entry->currency);
        $this->assertTrue($entry->isCredit);
        $this->assertSame('V08-001', $entry->endToEndId);
        $this->assertSame('V08 Debtor', $entry->counterpartyName);
    }
}
