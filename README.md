# SEPA Plugin for CakePHP

[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net/)
[![CakePHP](https://img.shields.io/badge/cakephp-%3E%3D%205.2-red.svg?style=flat-square)](https://cakephp.org/)
[![Status](https://img.shields.io/badge/status-0.x%20unstable-orange.svg?style=flat-square)](#status)

SEPA banking primitives for CakePHP 5.x: IBAN / BIC / Creditor ID validation and CAMT.053 / CAMT.054 parsing with German bank-quirk normalization.

> **Status: 0.x unstable.** API may break before 1.0. Pin to `^0.1` in production and read [CHANGELOG.md](CHANGELOG.md) before upgrading. Cut to 1.0 once the API has stabilized across two or more real consumers.

## What's in the box

Two sub-concerns that every DACH SaaS dealing with bank data eventually needs:

| Sub-area | Purpose | Key classes |
|---|---|---|
| **Iban** | IBAN / BIC / SEPA Creditor ID validation and lookup | `IbanValidator`, `BicResolver`, `CreditorIdValidator` |
| **Camt** | CAMT.053 account statements + CAMT.054 return notifications | `Camt053Parser`, `Camt054Parser`, `BankQuirkNormalizer`, `CamtEntry`, `CamtStatement`, `EndToEndIdStrategy` |

Each concern lives under its own sub-namespace (`Sepa\Iban\…`, `Sepa\Camt\…`) so internal boundaries stay clean.

## Installation

```bash
composer require dereuromark/cakephp-sepa
bin/cake plugin load Sepa
```

Requires **PHP 8.3+** and **CakePHP 5.2+**. Depends on:
- `jschaedl/iban-validation` for IBAN validation primitives
- `genkgo/camt` for CAMT parsing primitives
- temporary fork pin available when waiting on upstream CAMT fixes

## Quick start

### Validate an IBAN

```php
use Sepa\Iban\Service\IbanValidator;

$validator = new IbanValidator();
$validator->isValid('DE89370400440532013000');        // true
$validator->isValid('DE89 3704 0044 0532 0130 00');   // true (spaces stripped)
$validator->isValid('DE99370400440532013000');        // false (bad checksum)
$validator->isValid('de89370400440532013000');        // false (SEPA-strict: uppercase only)

$validator->normalize('de89 3704 0044 0532 0130 00'); // 'DE89370400440532013000'
$validator->countryCode('DE89370400440532013000');    // 'DE'
```

### Resolve BIC from German IBAN

```php
use Sepa\Iban\Service\BicResolver;

$directory = include '/path/to/your/blz-bic.php'; // map of 8-digit BLZ → BIC
$resolver = new BicResolver($directory);

$resolver->resolve('DE89370400440532013000');    // 'COBADEFFXXX' (or null if unknown)
$resolver->isKnown('DE89370400440532013000');    // true
$resolver->extractBankCode('DE89370400440532013000'); // '37040044'
```

### Validate a SEPA Creditor ID

```php
use Sepa\Iban\Service\CreditorIdValidator;

$validator = new CreditorIdValidator();
$validator->isValid('DE98ZZZ09999999999'); // true (Bundesbank example)
$validator->isValid('DE99ZZZ09999999999'); // false (bad ISO 7064 checksum)

$validator->countryCode('DE98ZZZ09999999999');      // 'DE'
$validator->businessCode('DE98ZZZ09999999999');     // 'ZZZ'
$validator->nationalIdentifier('DE98ZZZ09999999999'); // '09999999999'
```

### Parse a CAMT.053 bank statement

```php
use Sepa\Camt\Parser\Camt053Parser;

$parser = new Camt053Parser();
$result = $parser->parseFile('/path/to/statement.xml');
// or: $result = $parser->parse($xmlString);

foreach ($result->statements as $statement) {
    echo $statement->accountIban; // 'DE89370400440532013000'

    foreach ($statement->entries as $entry) {
        echo $entry->amount;                // '119.00'
        echo $entry->currency;              // 'EUR'
        echo $entry->isCredit ? '+' : '-';  // direction
        echo $entry->endToEndId;            // 'RE-2026-0001'
        echo $entry->counterpartyName;      // 'Max Mustermann'
        echo $entry->counterpartyIban;      // 'DE27100777770209299700'
        echo $entry->remittanceInformation; // 'Payment for invoice RE-2026-0001'
        echo $entry->bookingDate;           // Cake\I18n\Date
        echo $entry->valueDate;             // Cake\I18n\Date
    }
}
```

### Parse a CAMT.054 return notification (Rückläufer)

```php
use Sepa\Camt\Parser\Camt054Parser;

$parser = new Camt054Parser();
$result = $parser->parseFile('/path/to/returns.xml');

foreach ($result->statements[0]->entries as $entry) {
    echo $entry->returnReasonCode; // 'AC04' (account closed), 'MS03', etc.
    echo $entry->endToEndId;       // 'DUES-2026-001' — use this to find the local record
}
```

### Match incoming entries against local records

```php
use Sepa\Camt\Strategy\EndToEndIdStrategy;

$strategy = new EndToEndIdStrategy('/^(RE-\d{4}-\d{4})/');

foreach ($result->statements[0]->entries as $entry) {
    $key = $strategy->match($entry); // e.g. 'RE-2026-0001'
    if ($key !== null) {
        $invoice = $this->Invoices->find()->where(['invoice_number' => $key])->first();
        // ... mark as paid, emit PaymentReceived event, etc. ...
    }
}
```

### Normalize entries across bank variants

```php
use Sepa\Camt\Normalizer\BankQuirkNormalizer;

$normalizer = new BankQuirkNormalizer();
foreach ($result->statements[0]->entries as $entry) {
    $cleaned = $normalizer->normalize($entry);
    // Trimmed whitespace, uppercase reason codes, collapsed remittance spaces
}
```

## Documentation

- [docs/Iban.md](docs/Iban.md) — IBAN/BIC/Creditor ID validation, Bundesbank directory format, country-specific quirks
- [docs/Camt.md](docs/Camt.md) — CAMT.053/054 parsing, DTO shapes, auto-match strategies, bank normalization

## Testing

```bash
composer install
composer test      # PHPUnit — 53 tests against synthetic CAMT fixtures
composer stan      # PHPStan level 8
composer cs-check  # PhpCollective code style
```

All tests run in under a second. No external service dependencies — CAMT XML fixtures are vendored in `tests/Fixture/`.

## What's deliberately NOT here (0.1 scope)

- **pain.008 generator** — SEPA direct debit batch emission is a separate concern best suited to its own plugin. Applications that need it today should use `digitickets/sepa-xml` directly.
- **Bundesbank BLZ directory** — the 4MB quarterly-updated bank directory is intentionally NOT shipped in the package. Applications bring their own directory via `BicResolver`'s constructor. See [docs/Iban.md](docs/Iban.md) for format guidance.
- **Real bank CAMT samples** — the test fixtures are synthetic (spec-compliant but not based on proprietary bank-specific output). Applications targeting specific banks should add integration tests against their own samples.
- **FinTS / HBCI client** — direct bank API access for balance retrieval and payment submission is a large concern deliberately out of scope. Applications needing it should use `nemiah/php-fints`.

## Related plugins

Part of a family of focused DACH-compliance plugins for CakePHP 5.x:

- **`dereuromark/cakephp-compliance`** — GoBD retention, multi-tenant scoping, gap-free numbering, dual-approval.
- **`dereuromark/cakephp-accounting`** — §286 / §288 BGB dunning + DATEV CSV export.
- **`dereuromark/cakephp-sepa`** — this plugin. SEPA banking primitives.

## Contributing

PRs welcome. Please include tests, run PHPStan (`composer stan`) and PHPCS (`composer cs-check`) before submitting, and sign off commits per the DCO.

## License

MIT. See [LICENSE](LICENSE).
