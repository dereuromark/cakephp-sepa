<?php

declare(strict_types=1);

namespace Sepa\Iban\Service;

/**
 * Derive a BIC (SWIFT code) from a German IBAN by looking up the bank code
 * portion (BLZ — Bankleitzahl) in an injected directory.
 *
 * Applications provide their own directory: the plugin does NOT ship a full
 * Bundesbank "Bankenverzeichnis" because (a) it's a 4 MB file updated every
 * quarter and (b) the distribution license is ambiguous. See
 * <https://www.bundesbank.de/de/aufgaben/unbarer-zahlungsverkehr/serviceangebot/bankleitzahlen>
 * for the official source.
 *
 * Typical wiring:
 *
 * ```php
 * $directory = include '/path/to/blz-bic.php'; // your own data file
 * $resolver = new BicResolver($directory);
 * $bic = $resolver->resolve($member->iban);
 * ```
 *
 * For non-German IBANs the resolver returns `null`: BLZ-to-BIC mapping is
 * country-specific and the plugin intentionally scopes itself to the DACH
 * use case. Applications supporting other countries should inject a
 * country-specific resolver of their own.
 */
class BicResolver
{
    /**
     * @param array<string, string> $directory Map of 8-digit BLZ to BIC
     */
    public function __construct(protected array $directory = [])
    {
    }

    public function resolve(string $iban): ?string
    {
        $bankCode = $this->extractBankCode($iban);
        if ($bankCode === '') {
            return null;
        }

        return $this->directory[$bankCode] ?? null;
    }

    public function isKnown(string $iban): bool
    {
        return $this->resolve($iban) !== null;
    }

    public function extractBankCode(string $iban): string
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
        if (!str_starts_with($normalized, 'DE') || strlen($normalized) < 12) {
            return '';
        }

        return substr($normalized, 4, 8);
    }
}
