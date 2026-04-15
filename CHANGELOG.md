# Changelog

All notable changes to **Statamic Logbook** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Added

* (none)

### Changed

* (none)

### Fixed

* (none)

---

## [1.5.0] – 2026-04-15

### Added

* (none)

### Changed

* Updated CP maintenance action requests to submit URL-encoded form payloads for Laravel/Statamic CSRF compatibility.

### Fixed

* Fixed `CSRF token mismatch.` when triggering Logbook CP command CTAs (`Prune Logs` / `Flush Spool`).

---

## [1.4.0] – 2026-04-15

### Added

* Optional local spool ingestion mode for logs with no external queue dependency.
* `logbook:flush-spool` command for scheduled batched spool-to-DB ingestion.
* Spool operational reporting (queued files/bytes and failed files before/after flush).
* PHPUnit harness and targeted regression tests for audit defaults/action normalization and pulse listener guard.
* CP dashboard widget suite for overview cards, trends, and live pulse feed.
* CP action endpoints and header CTAs to run `logbook:prune` and `logbook:flush-spool` directly from Logbook utility.

### Changed

* Switched audit defaults to curated high-signal events, with optional discovery via `LOGBOOK_AUDIT_DISCOVER_EVENTS`.
* Normalized audit action naming for non-entry subjects to operation-oriented actions (`created|updated|deleted|event`).
* Added optional spool-first ingestion mode (`LOGBOOK_INGEST_MODE=spool`) to avoid request-time remote DB writes.
* Reduced trends dashboard query fan-out via grouped aggregate queries.
* Registered Logbook Artisan commands for web-invoked `Artisan::call(...)` usage from CP actions.

### Fixed

* Isolated audit persistence failures so audit DB issues do not break application requests.
* Prevented stale entry snapshot buildup by clearing cached pre-save state after consume.
* Made pulse filter binding idempotent to avoid duplicate listeners in repeated widget mounts.
* Surfaced flush failure paths and exception messages directly in CLI output.
* Normalized spool `created_at` values (including ISO8601) before DB insert.
* Prevented silent event drops by falling back to direct DB insert when spool enqueue fails.
* Fixed command availability error in CP-triggered execution (`The command "logbook:flush-spool" does not exist.`).

### Removed

* Removed unused `LogbookLoggerFactory` legacy class.

---

## [1.2.0] – 2026-04-13

### Added

* Dashboard widgets for:
  * 24h health overview cards
  * Daily stacked trends (system/error/audit)
  * Live pulse feed with in-widget filters
* Shared dashboard data service to power widget queries consistently
* Dedicated widgets registration for Stats/Trends/Pulse in provider boot

### Changed

* Replaced legacy single stats widget view with richer cards/trends/pulse widget set
* Updated widget templates to rely on native Statamic CP classes (Tailwind + CP components)
* Removed external frontend build dependency for widget styling/assets

### Fixed

* Widget registration/bootstrap compatibility by merging Statamic widget bindings at runtime
* Live pulse feed filtering behavior in Control Panel dashboard context

---

## [1.1.0] – 2026-04-08

### Added

* Automatic system log capture without manual `logging.php` wiring
* Runtime listener for Laravel `MessageLogged` events to write into Logbook DB
* Automatic Statamic event discovery for audit logging with configurable `exclude_events`
* Environment override for audit exclusions via `LOGBOOK_AUDIT_EXCLUDE_EVENTS`

### Changed

* Improved system log handler resilience to avoid app failures when log DB is unavailable
* Added safer level parsing fallback in logger factory
* Updated configuration/README to document system noise filters and audit event exclusion

### Fixed

* Reduced noisy framework/system messages from default system log capture

---

## [1.0.1] – 2025-01-XX

### Added

* System logs stored directly in database
* User audit logs with before/after change tracking
* Control Panel pages for:

  * System Logs
  * Audit Logs
* CSV export for system and audit logs
* Modal preview for log context and audit changes
* Analytics summaries (last 24h, top actions, levels)
* Configurable audit ignore fields
* Configurable maximum stored value length
* Configurable log retention with prune command
* Dedicated database connection via `.env`
* Permissions for viewing and exporting logs

### Changed

* Improved Control Panel UI layout and styling
* Optimized database inserts for log writes
* Normalized audit payload structure
* Safer handling of large and unicode values
* Improved filtering performance

### Fixed

* Database connection edge cases
* Unicode handling in modal previews
* Route registration issues in Control Panel utilities
* Audit log noise caused by timestamp fields

---

## [1.0.0] – 2025-01-XX

### Added

* Initial release of Statamic Logbook
* Core system logging functionality
* Core audit logging functionality
* Control Panel integration

---

## Notes

* Versions prior to `1.0.0` are considered pre-release and unsupported.
* Future releases may introduce breaking changes following semantic versioning.

---

## Upgrade Guide

### From 1.0.0 → 1.0.1

No breaking changes.
You may optionally publish updated config files if prompted.

