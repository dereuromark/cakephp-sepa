        # SEPA Plugin for CakePHP

        [![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
        [![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net/)
        [![Status](https://img.shields.io/badge/status-0.x%20unstable-orange.svg?style=flat-square)](#status)

        > **Status: 0.x unstable.** API may break before 1.0. Pin to `^0.1` in production and read the CHANGELOG before upgrading minor versions. Cut to `1.0` once the API has stabilized across two or more real consumers.

        A focused CakePHP 5.x plugin bundling the SEPA banking primitives every DACH vertical-SaaS application eventually needs:

1. **IBAN / BIC / Creditor ID** — CakePHP-native validator integration on top of `jschaedl/iban`, BIC derivation from IBAN via the Bundesbank bank directory, SEPA Creditor ID format validator (`DE\\d{2}ZZZ\\d{11}` with checksum), and a form helper for live validation hints.

2. **CAMT parsing** — wrapper on `genkgo/camt` with normalization across the German bank variants (Sparkasse, Volksbank, DKB, Commerzbank, Postbank, N26, Holvi). Emits domain events for payment receipt, debit return, and chargeback. Pluggable auto-match strategies for reconciling incoming bank entries against open invoices or SEPA debit batches.

Sub-namespaced under `Sepa\\Iban` and `Sepa\\Camt` to keep the internal boundaries clean.

        ## Features

        - **Iban**: CakePHP `Validator::add('iban', ...)` integration, `BicResolver` via vendored Bundesbank bank directory, SEPA `CreditorIdValidator`, `IbanFormHelper`.
- **Camt**: `Camt053Parser` for account statements, `Camt054Parser` for debit returns, normalization layer for Sparkasse / Volksbank / DKB / Commerzbank / Postbank / N26 / Holvi.
- Domain events: `PaymentReceived`, `DebitReturned`, `ChargebackIssued`.
- Pluggable `AutoMatchStrategy` interface — match by amount + Mandatsreferenz, by Verwendungszweck regex, or by fuzzy name.
- Built on `jschaedl/iban` and `genkgo/camt`.
- Both concerns co-versioned under one plugin.

        ## Structure

This plugin is internally organized into focused sub-areas under the main namespace:

### `Sepa\Iban`

- `Iban/Validation/IbanValidation`
- `Iban/Service/BicResolver`
- `Iban/Service/CreditorIdValidator`
- `Iban/View/Helper/IbanFormHelper`

### `Sepa\Camt`

- `Camt/Parser/Camt053Parser`
- `Camt/Parser/Camt054Parser`
- `Camt/Normalizer/BankQuirkNormalizer`
- `Camt/Event/PaymentReceived`
- `Camt/Event/DebitReturned`
- `Camt/Strategy/AutoMatchStrategyInterface`


        ## Installation

        Install via [composer](https://getcomposer.org):

        ```bash
        composer require dereuromark/cakephp-sepa
        bin/cake plugin load Sepa
        ```

        ## Usage

        > This is a 0.x skeleton. Usage examples will appear here as the API stabilizes. See the `docs/` folder for architecture notes and the `tests/` folder for working examples.

        ## Motivation

        This plugin is part of a three-plugin family extracted from real DACH vertical-SaaS products (landlord billing, freelancer invoicing, Vereinsverwaltung) where German legal and tax requirements shape the architecture:

        - **`dereuromark/cakephp-compliance`** — GoBD retention, multi-tenant scoping, gap-free numbering, dual-approval workflows. Every-request compliance plumbing.
        - **`dereuromark/cakephp-accounting`** — §286 / §288 BGB dunning calculators and DATEV CSV export. German accounting workflow.
        - **`dereuromark/cakephp-sepa`** — IBAN / BIC / Creditor ID validation and CAMT.053 / CAMT.054 parsing with German bank-quirk normalization. SEPA banking primitives.

        Each plugin bundles tightly-cohesive sub-concerns under sub-namespaces so installation is one `composer require` per domain area rather than a scattershot of micro-packages.

        ## Contributing

        PRs welcome. Please include tests, run PHPStan (`composer stan`) and PHPCS (`composer cs-check`) before submitting, and sign off commits per the DCO.

        ## License

        MIT. See [LICENSE](LICENSE).
