<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase\Iban;

use PHPUnit\Framework\TestCase;
use Sepa\Iban\Service\IbanValidator;

class IbanValidatorTest extends TestCase
{
    protected IbanValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new IbanValidator();
    }

    public function testValidGermanIbanIsAccepted(): void
    {
        $this->assertTrue($this->validator->isValid('DE89370400440532013000'));
    }

    public function testValidGermanIbanWithSpacesIsAccepted(): void
    {
        $this->assertTrue($this->validator->isValid('DE89 3704 0044 0532 0130 00'));
    }

    public function testIbanWithWrongChecksumRejected(): void
    {
        $this->assertFalse($this->validator->isValid('DE99370400440532013000'));
    }

    public function testIbanTooShortRejected(): void
    {
        $this->assertFalse($this->validator->isValid('DE89'));
    }

    public function testLowercaseCountryCodeRejected(): void
    {
        $this->assertFalse($this->validator->isValid('de89370400440532013000'));
    }

    public function testEmptyStringRejected(): void
    {
        $this->assertFalse($this->validator->isValid(''));
    }

    public function testValidAustrianIbanAccepted(): void
    {
        $this->assertTrue($this->validator->isValid('AT611904300234573201'));
    }

    public function testValidSwissIbanAccepted(): void
    {
        $this->assertTrue($this->validator->isValid('CH9300762011623852957'));
    }

    public function testNormalizeStripsSpacesAndUppercases(): void
    {
        $this->assertSame(
            'DE89370400440532013000',
            $this->validator->normalize('de89 3704 0044 0532 0130 00'),
        );
    }

    public function testCountryCodeExtraction(): void
    {
        $this->assertSame('DE', $this->validator->countryCode('DE89370400440532013000'));
        $this->assertSame('AT', $this->validator->countryCode('AT611904300234573201'));
    }
}
