<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase\Camt;

use Cake\I18n\Date;
use PHPUnit\Framework\TestCase;
use Sepa\Camt\Dto\CamtEntry;
use Sepa\Camt\Strategy\EndToEndIdStrategy;

class EndToEndIdStrategyTest extends TestCase
{
    protected function makeEntry(?string $endToEndId): CamtEntry
    {
        return new CamtEntry(
            amount: '100.00',
            currency: 'EUR',
            isCredit: true,
            bookingDate: Date::parse('2026-03-15'),
            valueDate: Date::parse('2026-03-15'),
            endToEndId: $endToEndId,
            remittanceInformation: null,
            counterpartyName: null,
            counterpartyIban: null,
        );
    }

    public function testReturnsEndToEndIdVerbatimWithNoPattern(): void
    {
        $strategy = new EndToEndIdStrategy();
        $this->assertSame(
            'RE-2026-0001',
            $strategy->match($this->makeEntry('RE-2026-0001')),
        );
    }

    public function testReturnsNullWhenEndToEndIdAbsent(): void
    {
        $strategy = new EndToEndIdStrategy();
        $this->assertNull($strategy->match($this->makeEntry(null)));
    }

    public function testReturnsNullWhenEndToEndIdEmpty(): void
    {
        $strategy = new EndToEndIdStrategy();
        $this->assertNull($strategy->match($this->makeEntry('')));
    }

    public function testExtractionPatternMatchesFirstCapturedGroup(): void
    {
        $strategy = new EndToEndIdStrategy('/^(RE-\d{4}-\d{4})/');
        $this->assertSame(
            'RE-2026-0001',
            $strategy->match($this->makeEntry('RE-2026-0001|ACCOUNT-7')),
        );
    }

    public function testExtractionPatternMissesReturnsNull(): void
    {
        $strategy = new EndToEndIdStrategy('/^RE-\d{4}-\d{4}$/');
        $this->assertNull($strategy->match($this->makeEntry('NOT-MATCHING')));
    }
}
