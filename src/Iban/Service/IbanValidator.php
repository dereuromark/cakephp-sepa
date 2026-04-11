<?php

declare(strict_types=1);

namespace Sepa\Iban\Service;

use Iban\Validation\Validator;
use Throwable;

/**
 * Thin wrapper around `jschaedl/iban-validation` providing a boolean-returning
 * API that does not throw on invalid input.
 *
 * The upstream `Validator::validate()` throws on the first violation; for
 * most consumers (form validation, conditional rendering, bulk normalization)
 * a boolean return is more useful than exception-driven control flow.
 *
 * Normalization removes whitespace and forces upper-case — the canonical
 * machine format for IBANs. Country code extraction returns the first two
 * characters as-is (assuming normalized input).
 */
class IbanValidator
{
    protected Validator $validator;

    public function __construct(?Validator $validator = null)
    {
        $this->validator = $validator ?? new Validator();
    }

    public function isValid(string $iban): bool
    {
        if ($iban === '') {
            return false;
        }
        // SEPA-strict: reject lowercase. The spec defines IBAN as uppercase-only
        // for machine processing; form input with spaces is allowed but letters
        // must be upper-case.
        $stripped = preg_replace('/\s+/', '', $iban) ?? '';
        if ($stripped !== strtoupper($stripped)) {
            return false;
        }

        try {
            return $this->validator->validate($iban);
        } catch (Throwable) {
            return false;
        }
    }

    public function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    public function countryCode(string $iban): string
    {
        return substr($this->normalize($iban), 0, 2);
    }
}
