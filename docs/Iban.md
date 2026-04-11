# Iban — IBAN, BIC & Creditor ID Validation

The `Sepa\Iban` sub-area provides three validation services that every SEPA-aware application needs:

1. **IBAN validation** — spec-correct ISO 13616 validation with SEPA-strict uppercase enforcement
2. **BIC resolution** — derive the SWIFT code from a German IBAN via an injected Bundesbank directory
3. **Creditor ID validation** — SEPA Gläubiger-Identifikationsnummer format + ISO 7064 mod 97-10 checksum

All three are thin, focused services that wrap or extend well-known specs. The plugin does not reinvent IBAN math — it leverages `jschaedl/iban-validation` for the checksum algorithm and adds the SEPA-specific wrappers that any DACH application needs.

---

## IbanValidator

Wraps `jschaedl/iban-validation` with:

1. A boolean-returning `isValid()` that doesn't throw on invalid input (the upstream throws, which is inconvenient for form validation)
2. SEPA-strict uppercase enforcement (the upstream is lenient; real SEPA messages reject lowercase)
3. A normalizer that strips whitespace and forces uppercase
4. A country code extractor

### API

```php
use Sepa\Iban\Service\IbanValidator;

$validator = new IbanValidator();

// Boolean check
$validator->isValid('DE89370400440532013000');  // true
$validator->isValid('DE89 3704 0044 0532 0130 00'); // true — spaces allowed in input
$validator->isValid('DE99370400440532013000');  // false — bad checksum
$validator->isValid('de89370400440532013000');  // false — SEPA-strict: uppercase only

// Normalization
$validator->normalize('de89 3704 0044 0532 0130 00');
// → 'DE89370400440532013000'

// Country code
$validator->countryCode('DE89370400440532013000'); // 'DE'
$validator->countryCode('AT611904300234573201');   // 'AT'
```

### Why SEPA-strict?

Upstream `jschaedl/iban-validation` accepts `de89370400440532013000` (lowercase `de`) because technically the ISO 13616 algorithm only operates on the digits. However, SEPA messages (pain.001, pain.008, CAMT) are defined as upper-case-only. An IBAN entered in lowercase will pass the upstream validator but fail the bank's SEPA parser. We reject it up front.

Applications that want the lenient behavior can construct the upstream `\Iban\Validation\Validator` directly.

---

## BicResolver

Derive a BIC (SWIFT code) from a German IBAN by looking up the bank code portion (BLZ — Bankleitzahl) in an injected directory.

### Why not ship the directory?

The Deutsche Bundesbank Bankenverzeichnis is a 4MB file updated every quarter. We deliberately do NOT ship it because:

1. **Size**: 4MB vendored into a Composer package pollutes install size.
2. **Staleness**: the package would go out of date within 3 months of every release.
3. **License**: the distribution license is ambiguous (free to use but redistribution terms are unclear).

Instead, applications fetch the directory themselves and inject it.

### Where to get the directory

Official source: <https://www.bundesbank.de/de/aufgaben/unbarer-zahlungsverkehr/serviceangebot/bankleitzahlen-846108>

The Bundesbank publishes a fixed-width text file every quarter. Applications parse it once into a PHP map file (BLZ → BIC) and load that map at runtime.

### Expected directory format

A flat array of 8-digit BLZ → BIC:

```php
// File: /etc/app/blz-bic.php
return [
    '10000000' => 'MARKDEF1100',    // Bundesbank Berlin
    '10070000' => 'DEUTDEBBXXX',    // Deutsche Bank Berlin
    '10070024' => 'DEUTDEDBBER',    // Deutsche Bank Berlin (private clients)
    '37040044' => 'COBADEFFXXX',    // Commerzbank Köln
    // ... 2000+ entries ...
];
```

### Usage

```php
use Sepa\Iban\Service\BicResolver;

$directory = include '/etc/app/blz-bic.php';
$resolver = new BicResolver($directory);

$resolver->resolve('DE89370400440532013000');
// → 'COBADEFFXXX'

$resolver->resolve('DE89100500000000000001');
// → null (BLZ 10050000 not in directory)

$resolver->isKnown('DE89370400440532013000');
// → true

// BLZ extraction alone:
$resolver->extractBankCode('DE89370400440532013000');
// → '37040044'

// Non-German IBAN returns null:
$resolver->resolve('AT611904300234573201');
// → null (BicResolver is intentionally DE-only)
```

### Why DE-only?

BLZ-to-BIC mapping is country-specific:

- **DE**: 8-digit BLZ at position 4–11 of the IBAN
- **AT**: 5-digit Bankleitzahl at position 4–8
- **CH**: 5-digit clearing number at position 4–8
- **FR**: 5-digit bank code at position 4–8
- **ES**: 4-digit entity code at position 4–7

Each country has its own directory format and its own publishing authority. The plugin deliberately scopes itself to DE because DACH is the target audience. Applications supporting AT or CH should inject country-specific resolvers of their own (same interface, different directory).

---

## CreditorIdValidator

SEPA Creditor IDs (Gläubiger-Identifikationsnummer) identify the creditor in a SEPA direct debit flow. Any organization that collects money via SEPA-Lastschrift has one, assigned by the Deutsche Bundesbank.

### Format

```
AA XX BBB NNNNNNNNNN...
```

- `AA` — ISO 3166 country code (2 upper-case letters)
- `XX` — ISO 7064 mod 97-10 check digits (2 numeric)
- `BBB` — Creditor Business Code (3 characters, `ZZZ` = unspecified)
- `NNN...` — national identifier (variable length, alphanumeric)

German creditor IDs are 18 characters total:

```
DE98 ZZZ 09999999999
```

### Checksum algorithm

The check digits are computed per ISO 7064 mod 97-10 using only the country code letters + check digits + national identifier — the Creditor Business Code (`ZZZ`) is **excluded** from the checksum calculation.

1. Rearrange: `nationalIdentifier + countryCode + "00"`
2. Convert letters to numbers: A=10, B=11, ..., Z=35
3. Compute `remainder = numeric mod 97`
4. `expectedCheck = 98 - remainder`
5. `expectedCheck` should match the two check digits in the original string

The validator in this plugin implements exactly this algorithm using `bcmath` for the modulo operation (numbers can exceed 64-bit integer range).

### API

```php
use Sepa\Iban\Service\CreditorIdValidator;

$validator = new CreditorIdValidator();

$validator->isValid('DE98ZZZ09999999999');  // true (Bundesbank example)
$validator->isValid('AT88ZZZ00000000001');  // true (valid AT example)
$validator->isValid('DE99ZZZ09999999999');  // false (bad check digits)
$validator->isValid('de98zzz09999999999');  // false (lowercase rejected)
$validator->isValid('DE98 ZZZ 099');        // false (whitespace rejected)

// Component extraction
$validator->countryCode('DE98ZZZ09999999999');          // 'DE'
$validator->businessCode('DE98ZZZ09999999999');         // 'ZZZ'
$validator->nationalIdentifier('DE98ZZZ09999999999');   // '09999999999'
```

### Where to get a Creditor ID

German Vereine and businesses apply at the Deutsche Bundesbank: <https://extranet.bundesbank.de/scp/>

The application is free. Austrian entities use OeNB, Swiss entities use SIX.

### Why validate the checksum?

Without the checksum verification, a typo in the creditor ID wouldn't be caught at form-entry time — it would fail at SEPA submission days or weeks later. Validating the checksum at the edge catches ~97% of common typos immediately.

---

## Integration with CakePHP form validation

### As a Validator rule

```php
use Sepa\Iban\Service\IbanValidator;

class MembersTable extends Table
{
    public function validationDefault(Validator $validator): Validator
    {
        $validator->add('iban', 'validFormat', [
            'rule' => function ($value, $context) {
                return (new IbanValidator())->isValid($value);
            },
            'message' => 'Please enter a valid IBAN.',
        ]);

        return $validator;
    }
}
```

### As a model event for auto-normalization

```php
public function beforeMarshal(EventInterface $event, ArrayObject $data): void
{
    if (!empty($data['iban'])) {
        $data['iban'] = (new IbanValidator())->normalize($data['iban']);
    }
}
```

This way the user can paste `DE89 3704 0044 0532 0130 00` with spaces and the stored value is normalized to `DE89370400440532013000`.

---

## Test suite

28 passing tests in `tests/TestCase/Iban/`:

### `IbanValidatorTest` (10)
- Valid German IBAN accepted
- Valid German IBAN with spaces accepted
- Wrong checksum rejected
- Too-short input rejected
- Lowercase rejected (SEPA-strict)
- Empty string rejected
- Valid Austrian IBAN accepted
- Valid Swiss IBAN accepted
- Normalize strips spaces and uppercases
- Country code extraction

### `BicResolverTest` (8)
- Resolves German IBAN to known BIC from directory
- Returns null for unknown bank code
- Accepts IBAN with spaces
- Non-German IBAN returns null from German directory
- Extracts bank code from German IBAN
- Extract bank code strips spaces
- Malformed IBAN returns empty bank code
- `isKnown` reports correctly

### `CreditorIdValidatorTest` (10)
- Valid German creditor ID passes (Bundesbank example)
- Valid Austrian creditor ID passes
- Too-short string rejected
- Invalid check digits rejected
- Missing business code rejected
- Lowercase rejected
- Whitespace rejected
- Country code extraction
- Business code extraction
- National identifier extraction
