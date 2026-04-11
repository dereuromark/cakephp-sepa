<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase\Camt;

use PHPUnit\Framework\TestCase;
use Sepa\Camt\Exception\CamtParseException;
use Sepa\Camt\Parser\Camt054Parser;

class Camt054ParserTest extends TestCase
{
    protected Camt054Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Camt054Parser();
    }

    public function testParsesNotificationProducesOneStatement(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt054_sample.xml');
        $result = $this->parser->parse($xml);
        $this->assertCount(1, $result->statements);
    }

    public function testReturnEntryIsDebit(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt054_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertFalse($entry->isCredit);
    }

    public function testEndToEndIdIsPreserved(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt054_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertSame('DUES-2026-001', $entry->endToEndId);
    }

    public function testReturnReasonCodeIsExtracted(): void
    {
        $xml = (string)file_get_contents(ROOT . DS . 'tests' . DS . 'Fixture' . DS . 'camt054_sample.xml');
        $result = $this->parser->parse($xml);
        $entry = $result->statements[0]->entries[0];
        $this->assertSame('AC04', $entry->returnReasonCode);
    }

    public function testInvalidXmlRaises(): void
    {
        $this->expectException(CamtParseException::class);
        $this->parser->parse('<bogus/>');
    }
}
