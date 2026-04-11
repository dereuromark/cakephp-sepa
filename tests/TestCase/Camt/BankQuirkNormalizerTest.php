<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase\Camt;

use Cake\I18n\Date;
use PHPUnit\Framework\TestCase;
use Sepa\Camt\Dto\CamtEntry;
use Sepa\Camt\Normalizer\BankQuirkNormalizer;

class BankQuirkNormalizerTest extends TestCase
{
    protected function makeEntry(array $overrides = []): CamtEntry
    {
        $defaults = [
            'amount' => '119.00',
            'currency' => 'EUR',
            'isCredit' => true,
            'bookingDate' => Date::parse('2026-03-15'),
            'valueDate' => Date::parse('2026-03-15'),
            'endToEndId' => 'RE-2026-0001',
            'remittanceInformation' => 'Invoice RE-2026-0001',
            'counterpartyName' => 'Max Mustermann',
            'counterpartyIban' => 'DE27100777770209299700',
            'returnReasonCode' => null,
        ];
        $merged = array_merge($defaults, $overrides);

        return new CamtEntry(...$merged);
    }

    public function testPassThroughDoesNotMutateFields(): void
    {
        $normalizer = new BankQuirkNormalizer();
        $original = $this->makeEntry();
        $result = $normalizer->normalize($original);
        $this->assertSame('119.00', $result->amount);
        $this->assertSame('RE-2026-0001', $result->endToEndId);
    }

    public function testStripsRedundantWhitespaceInRemittance(): void
    {
        $normalizer = new BankQuirkNormalizer();
        $entry = $this->makeEntry(['remittanceInformation' => '  RE-2026  ']);
        $result = $normalizer->normalize($entry);
        $this->assertSame('RE-2026', $result->remittanceInformation);
    }

    public function testTrimsCounterpartyName(): void
    {
        $normalizer = new BankQuirkNormalizer();
        $entry = $this->makeEntry(['counterpartyName' => '   Max Mustermann   ']);
        $result = $normalizer->normalize($entry);
        $this->assertSame('Max Mustermann', $result->counterpartyName);
    }

    public function testNullRemittanceStaysNull(): void
    {
        $normalizer = new BankQuirkNormalizer();
        $entry = $this->makeEntry(['remittanceInformation' => null]);
        $result = $normalizer->normalize($entry);
        $this->assertNull($result->remittanceInformation);
    }

    public function testNormalizesUppercaseReturnCode(): void
    {
        $normalizer = new BankQuirkNormalizer();
        $entry = $this->makeEntry(['returnReasonCode' => 'ac04']);
        $result = $normalizer->normalize($entry);
        $this->assertSame('AC04', $result->returnReasonCode);
    }
}
