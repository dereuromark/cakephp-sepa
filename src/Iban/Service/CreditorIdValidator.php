<?php

declare(strict_types=1);

namespace Sepa\Iban\Service;

/**
 * Validate SEPA Creditor IDs (Gläubiger-Identifikationsnummer).
 *
 * Format (18 characters for Germany, variable total length for other
 * countries):
 *
 *   AA XX BBB NNNNNNNNNN...
 *   AA = ISO 3166 country code (2 upper-case letters)
 *   XX = ISO 7064 mod 97-10 check digits (2 numeric)
 *   BBB = Creditor Business Code (3 chars, 'ZZZ' = unspecified)
 *   NNN... = National identifier (variable length, A-Z0-9)
 *
 * The check digits are computed per ISO 7064 mod 97-10 using only the
 * country-code letters + check digits + national identifier — the Creditor
 * Business Code is excluded from the checksum. See §14 Bundesbank
 * Zahlungsverkehrsrichtlinien for the full spec.
 */
class CreditorIdValidator
{
    public function isValid(string $creditorId): bool
    {
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{3}[A-Z0-9]+$/', $creditorId)) {
            return false;
        }
        if (strlen($creditorId) < 8) {
            return false;
        }

        return $this->verifyChecksum($creditorId);
    }

    public function countryCode(string $creditorId): string
    {
        return substr($creditorId, 0, 2);
    }

    public function businessCode(string $creditorId): string
    {
        return substr($creditorId, 4, 3);
    }

    public function nationalIdentifier(string $creditorId): string
    {
        return substr($creditorId, 7);
    }

    /**
     * ISO 7064 mod 97-10 checksum.
     *
     * Rearranged string: nationalIdentifier + countryCode + "00"
     * Letters are converted to numbers via A=10, B=11, ..., Z=35.
     * The result modulo 97 must equal `98 - checkDigits`.
     */
    protected function verifyChecksum(string $creditorId): bool
    {
        $check = substr($creditorId, 2, 2);
        $countryCode = $this->countryCode($creditorId);
        $nationalId = $this->nationalIdentifier($creditorId);

        $rearranged = $nationalId . $countryCode . '00';
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string)(ord($char) - 55) : $char;
        }
        if (!is_numeric($numeric)) {
            return false;
        }
        $remainder = (int)bcmod($numeric, '97');
        $expected = 98 - $remainder;

        return sprintf('%02d', $expected) === $check;
    }
}
