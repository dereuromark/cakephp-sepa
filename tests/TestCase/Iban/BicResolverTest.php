<?php

declare(strict_types=1);

namespace Sepa\Test\TestCase\Iban;

use PHPUnit\Framework\TestCase;
use Sepa\Iban\Service\BicResolver;

class BicResolverTest extends TestCase
{
    public function testResolvesGermanIbanToKnownBic(): void
    {
        $resolver = new BicResolver([
            '37040044' => 'COBADEFFXXX', // Commerzbank Köln
        ]);
        $this->assertSame(
            'COBADEFFXXX',
            $resolver->resolve('DE89370400440532013000'),
        );
    }

    public function testReturnsNullForUnknownBankCode(): void
    {
        $resolver = new BicResolver([]);
        $this->assertNull($resolver->resolve('DE89370400440532013000'));
    }

    public function testAcceptsIbanWithSpaces(): void
    {
        $resolver = new BicResolver([
            '37040044' => 'COBADEFFXXX',
        ]);
        $this->assertSame(
            'COBADEFFXXX',
            $resolver->resolve('DE89 3704 0044 0532 0130 00'),
        );
    }

    public function testNonGermanIbanReturnsNullFromGermanDirectory(): void
    {
        // A directory built for DE doesn't help for AT
        $resolver = new BicResolver([
            '37040044' => 'COBADEFFXXX',
        ]);
        $this->assertNull($resolver->resolve('AT611904300234573201'));
    }

    public function testExtractBankCodeFromGermanIban(): void
    {
        $resolver = new BicResolver([]);
        $this->assertSame(
            '37040044',
            $resolver->extractBankCode('DE89370400440532013000'),
        );
    }

    public function testExtractBankCodeStripsSpaces(): void
    {
        $resolver = new BicResolver([]);
        $this->assertSame(
            '37040044',
            $resolver->extractBankCode('DE89 3704 0044 0532 0130 00'),
        );
    }

    public function testMalformedIbanReturnsEmptyBankCode(): void
    {
        $resolver = new BicResolver([]);
        $this->assertSame('', $resolver->extractBankCode('INVALID'));
    }

    public function testKnownReturnsTrueOnlyWhenPresent(): void
    {
        $resolver = new BicResolver([
            '37040044' => 'COBADEFFXXX',
        ]);
        $this->assertTrue($resolver->isKnown('DE89370400440532013000'));
        $this->assertFalse($resolver->isKnown('DE89100500000000000001'));
    }
}
