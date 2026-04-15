# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial plugin skeleton.
- `Camt052Parser` for CAMT.052 interim / intraday account reports
  (`BkToCstmrAcctRpt`). Normalizes entries into the same `CamtResult` shape
  as `Camt053Parser`, so callers can feed 052 documents through the same
  matching pipeline. Supports `camt.052.001.01`–`.08` via the underlying
  `genkgo/camt` reader's auto-detection.
