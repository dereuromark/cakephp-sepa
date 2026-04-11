<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase\Iban;

use PHPUnit\Framework\TestCase;
use Sepa\Iban\Service\CreditorIdValidator;

class CreditorIdValidatorTest extends TestCase
{
    protected CreditorIdValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new CreditorIdValidator();
    }

    public function testValidGermanCreditorIdPassesOfficialExample(): void
    {
        // Official example from Deutsche Bundesbank documentation
        $this->assertTrue($this->validator->isValid('DE98ZZZ09999999999'));
    }

    public function testValidAustrianCreditorIdPasses(): void
    {
        // AT format with valid ISO 7064 mod 97-10 check digits
        $this->assertTrue($this->validator->isValid('AT88ZZZ00000000001'));
    }

    public function testTooShortStringRejected(): void
    {
        $this->assertFalse($this->validator->isValid('DE98'));
    }

    public function testInvalidCheckDigitsRejected(): void
    {
        // Mutated check digits
        $this->assertFalse($this->validator->isValid('DE99ZZZ09999999999'));
    }

    public function testMissingBusinessCodeRejected(): void
    {
        $this->assertFalse($this->validator->isValid('DE98___09999999999'));
    }

    public function testLowercaseRejected(): void
    {
        $this->assertFalse($this->validator->isValid('de98zzz09999999999'));
    }

    public function testWhitespaceRejected(): void
    {
        $this->assertFalse($this->validator->isValid('DE98 ZZZ 099 999 999 99'));
    }

    public function testCountryCodeExtraction(): void
    {
        $this->assertSame('DE', $this->validator->countryCode('DE98ZZZ09999999999'));
    }

    public function testBusinessCodeExtraction(): void
    {
        $this->assertSame('ZZZ', $this->validator->businessCode('DE98ZZZ09999999999'));
    }

    public function testNationalIdentifierExtraction(): void
    {
        $this->assertSame('09999999999', $this->validator->nationalIdentifier('DE98ZZZ09999999999'));
    }
}
