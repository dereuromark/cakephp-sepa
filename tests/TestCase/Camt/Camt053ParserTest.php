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
}
