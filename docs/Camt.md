# Camt — CAMT.053 & CAMT.054 Parsing

CAMT (Cash Management) is the ISO 20022 XML format banks use to report account activity. Two variants matter for SaaS reconciliation:

- **CAMT.053** — *Bank-to-Customer Statement*. Daily or periodic account statement with all booked entries. Used for general reconciliation: "did this invoice get paid?"
- **CAMT.054** — *Bank-to-Customer Debit/Credit Notification*. Used for immediate debit return notifications (Rückläufer): "your SEPA debit against member X failed because of reason AC04."

The `Sepa\Camt` sub-area parses both formats into normalized DTOs, with extraction of the fields that actually matter for auto-matching against local invoices and dues items.

---

## Core DTOs

### CamtResult

```php
final class CamtResult
{
    /** @var list<CamtStatement> */
    public readonly array $statements;
}
```

A single file produces one `CamtResult` with one or more `CamtStatement` children (most files have one statement; some banks batch multiple accounts).

### CamtStatement

```php
final class CamtStatement
{
    public readonly string $id;
    public readonly string $accountIban;
    public readonly ?string $accountBic;
    public readonly string $currency;
    /** @var list<CamtEntry> */
    public readonly array $entries;
}
```

### CamtEntry

```php
final class CamtEntry
{
    public readonly string $amount;            // decimal string '119.00'
    public readonly string $currency;          // 'EUR'
    public readonly bool $isCredit;            // true for incoming, false for outgoing
    public readonly Date $bookingDate;
    public readonly Date $valueDate;
    public readonly ?string $endToEndId;       // SEPA reference — use for matching
    public readonly ?string $remittanceInformation; // Verwendungszweck
    public readonly ?string $counterpartyName; // debtor/creditor name
    public readonly ?string $counterpartyIban;
    public readonly ?string $returnReasonCode; // AC04, MS03, etc. (CAMT.054 only)
}
```

---

## Camt053Parser — account statements

### Usage

```php
use Sepa\Camt\Parser\Camt053Parser;

$parser = new Camt053Parser();

// From string
$result = $parser->parse($xmlString);

// From file
$result = $parser->parseFile('/path/to/statement.xml');
```

### Error handling

All underlying `genkgo/camt` parsing errors are wrapped in `Sepa\Camt\Exception\CamtParseException` so consumers catch a single plugin-specific type:

```php
try {
    $result = $parser->parseFile($path);
} catch (\Sepa\Camt\Exception\CamtParseException $e) {
    $this->log('Failed to parse CAMT.053: ' . $e->getMessage());
    return;
}
```

### Supported CAMT.053 versions

Via `genkgo/camt`:

| Version | Supported |
|---|---|
| camt.053.001.01 | ❌ |
| camt.053.001.02 | ✅ (most common in DACH) |
| camt.053.001.03 | ✅ |
| camt.053.001.04 | ✅ |
| camt.053.001.08 | ✅ (newer, ISO 20022 2019) |

The parser auto-detects the version from the XML namespace.

---

## Camt054Parser — return notifications

### Usage

```php
use Sepa\Camt\Parser\Camt054Parser;

$parser = new Camt054Parser();
$result = $parser->parseFile('/path/to/returns.xml');
```

### The return-reason-code field

CAMT.054 entries that represent returned debits (Rückläufer) carry a return reason code in the `RtrInf/Rsn/Cd` path. `Camt054Parser` extracts this into `CamtEntry::$returnReasonCode`.

### Common SEPA return reason codes

| Code | Meaning | Typical action |
|---|---|---|
| `AC01` | Account identifier incorrect | Contact member, correct IBAN |
| `AC04` | Account closed | Write off, remove mandate |
| `AC06` | Account blocked | Contact member, retry next cycle |
| `AG01` | Transaction forbidden | Escalate — legal issue |
| `AG02` | Invalid bank operation code | Bank config issue |
| `AM04` | Insufficient funds | Retry in N days |
| `BE05` | Unrecognized initiating party | Creditor ID mismatch |
| `FF01` | Invalid file format | Check pain.008 emission |
| `MD01` | No mandate | Member revoked; contact |
| `MD02` | Missing mandatory information | Check mandate payload |
| `MD06` | Refund requested by debtor | Full refund; retry not allowed |
| `MD07` | End customer deceased | Remove membership |
| `MS02` | Not specified by customer | Investigate |
| `MS03` | Not specified by agent | Retry or investigate |
| `RC01` | Bank identifier incorrect | Correct BIC |
| `RR01` | Regulatory reasons — debtor bank | Escalate |
| `RR02` | Regulatory reasons — creditor bank | Escalate |
| `RR03` | Regulatory reasons — both | Escalate |
| `RR04` | Regulatory reasons — other | Investigate |

Consumers typically group these into action categories:

```php
$actionByCategory = match ($entry->returnReasonCode) {
    'AC04', 'MD07' => 'remove_membership',
    'AC06', 'AM04' => 'retry_next_cycle',
    'AC01', 'RC01' => 'contact_member',
    default        => 'investigate',
};
```

---

## BankQuirkNormalizer

### What it does (0.1)

A thin pass-through normalization pipeline that fixes the common cross-bank inconsistencies:

1. Trim leading/trailing whitespace on free-text fields (`endToEndId`, `counterpartyName`, `counterpartyIban`)
2. Collapse internal runs of whitespace in `remittanceInformation` to single spaces
3. Upper-case the `returnReasonCode` (some banks emit lowercase)

### API

```php
use Sepa\Camt\Normalizer\BankQuirkNormalizer;

$normalizer = new BankQuirkNormalizer();
$cleaned = $normalizer->normalize($entry);
// Returns a new CamtEntry; the original is not mutated
```

### Future per-bank rules (deferred to 0.2)

Real banks have idiosyncratic quirks that are best handled by dedicated rules:

- **Sparkasse**: sometimes pads EndToEndId with trailing whitespace
- **Volksbank**: occasionally wraps remittance lines at 27 characters with `+` continuation markers
- **DKB**: uses `/` as separator in structured remittance
- **Commerzbank**: may emit reason codes in lowercase
- **N26**: often uses structured remittance where others use unstructured

These patterns land as a pluggable pipeline in 0.2 once real customer samples are available. The interface stays backwards-compatible: callers still call `normalize($entry)` and get a cleaned entry.

---

## AutoMatchStrategyInterface

### Contract

```php
interface AutoMatchStrategyInterface
{
    /**
     * Return a match key for the entry, or null if no match was found.
     */
    public function match(CamtEntry $entry): ?string;
}
```

The plugin does not own the matching semantics — only the hook. The match key is whatever your application considers an invoice/dues-item identifier.

### Shipped strategy: EndToEndIdStrategy

Match by examining the `endToEndId` field:

```php
use Sepa\Camt\Strategy\EndToEndIdStrategy;

// Return EndToEndId verbatim
$strategy = new EndToEndIdStrategy();
$strategy->match($entry); // 'RE-2026-0001' or null

// Extract via regex (useful when EndToEndId contains composite data)
$strategy = new EndToEndIdStrategy('/^(RE-\d{4}-\d{4})/');
$strategy->match($entry); // captures the first group
```

### Writing your own strategy

```php
use Sepa\Camt\Dto\CamtEntry;
use Sepa\Camt\Strategy\AutoMatchStrategyInterface;

class RemittanceRegexStrategy implements AutoMatchStrategyInterface
{
    public function __construct(
        private readonly string $pattern,
    ) {
    }

    public function match(CamtEntry $entry): ?string
    {
        if ($entry->remittanceInformation === null) {
            return null;
        }
        if (preg_match($this->pattern, $entry->remittanceInformation, $m) === 1) {
            return $m[1] ?? $m[0];
        }
        return null;
    }
}
```

### Composing strategies

A typical production flow tries multiple strategies in order:

```php
$strategies = [
    new EndToEndIdStrategy('/^(RE-\d{4}-\d{4})/'),
    new RemittanceRegexStrategy('/Rechnung\s+(RE-\d{4}-\d{4})/i'),
    new CounterpartyNameStrategy($knownDebtors),
];

foreach ($result->statements[0]->entries as $entry) {
    $entry = $normalizer->normalize($entry);
    foreach ($strategies as $strategy) {
        $key = $strategy->match($entry);
        if ($key !== null) {
            $this->reconcile($key, $entry);
            continue 2;
        }
    }
    $this->queueForManualReview($entry);
}
```

---

## Domain events

### PaymentReceived

Emitted when a parsed CAMT.053 entry represents an incoming credit to the account holder:

```php
use Sepa\Camt\Event\PaymentReceived;

foreach ($result->statements[0]->entries as $entry) {
    if ($entry->isCredit) {
        $this->getEventManager()->dispatch(new Event('Sepa.paymentReceived', $this, [
            'event' => new PaymentReceived($entry),
        ]));
    }
}
```

### DebitReturned

Emitted when a parsed CAMT.054 entry represents a returned SEPA debit:

```php
use Sepa\Camt\Event\DebitReturned;

foreach ($result->statements[0]->entries as $entry) {
    if (!$entry->isCredit && $entry->returnReasonCode !== null) {
        $this->getEventManager()->dispatch(new Event('Sepa.debitReturned', $this, [
            'event' => new DebitReturned($entry, $entry->returnReasonCode),
        ]));
    }
}
```

---

## Testing strategy

### Synthetic fixtures

The plugin's tests use hand-written XML fixtures based on the ISO 20022 spec, stored in `tests/Fixture/`. These are spec-compliant but **not** derived from proprietary bank output. Applications targeting specific banks should add integration tests against their own samples.

### Why not real bank samples?

Real CAMT files from banks contain proprietary formatting quirks that only become visible when you have real data. We deliberately don't ship such samples because (a) they contain customer-identifying information that can't be redistributed and (b) they differ across banks so one sample wouldn't prove anything general.

The 0.1 tests verify the **spec compliance path**. The **bank compatibility path** is the responsibility of each application that adopts the plugin — typically wiring a test suite against an anonymized copy of each bank's output.

---

## Test suite

25 passing tests in `tests/TestCase/Camt/`:

### `Camt053ParserTest` (9)
- Parses sample file produces one statement
- Statement has account IBAN
- Statement carries entries
- Entry carries amount and direction
- Entry carries EndToEndId
- Entry carries remittance information
- Entry carries debtor name
- Parses file from disk
- Invalid XML throws `CamtParseException`

### `Camt054ParserTest` (5)
- Parses notification produces one statement
- Return entry is debit
- EndToEndId preserved
- Return reason code extracted
- Invalid XML raises

### `BankQuirkNormalizerTest` (5)
- Pass-through does not mutate fields
- Strips redundant whitespace in remittance
- Trims counterparty name
- Null remittance stays null
- Normalizes uppercase return code

### `EndToEndIdStrategyTest` (5)
- Returns EndToEndId verbatim with no pattern
- Returns null when EndToEndId absent
- Returns null when EndToEndId empty
- Extraction pattern matches first captured group
- Extraction pattern misses returns null
