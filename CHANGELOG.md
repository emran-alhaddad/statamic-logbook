# Changelog

All notable changes to **Statamic Logbook** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

The next release (`2.0.0`) adds first-class Statamic 6 support while keeping
Statamic 4 and 5 working from the same branch. Statamic 3 continues on the
dedicated `1.x` LTS branch.

### Added

* **Statamic 6 support.** The addon now boots cleanly on Statamic 6 without
  clobbering the core `statamic.widgets` extension binding.
* **`Audit\EventMap`**: a per-major curated event registry (majors 3–6) that
  resolves the running Statamic major from `Statamic::version()` or
  `vendor/composer/installed.json` and returns only the event classes that
  exist in that major. Missing or renamed events are silently dropped rather
  than producing class-not-found fatals at boot.
* **`Widgets\Registry\WidgetRegistryShim`**: a capability-gated back-compat
  helper that only re-registers Logbook widgets when the core
  `statamic.extensions` binding does not already contain our handles. On
  Statamic 6 this is a no-op; on Statamic 5 it retains the historical
  widget-registration safety net.
* **New unit tests**: `EventMapTest`, `StatamicAuditSubscriberResolutionTest`,
  `WidgetRegistryShimTest` covering per-major event resolution, silent
  filtering of missing event classes, exclusion semantics, and the widget
  shim's idempotency.
* **`orchestra/testbench`** (dev dependency) for future Laravel application
  harness and integration tests.

### Changed

* **BREAKING – config surface.** `config/logbook.php` no longer references
  `\Statamic\Events\*::class` constants directly. `audit_logs.events` and
  `audit_logs.exclude_events` are now **string-based allow/deny lists** and
  default to the curated per-major list produced by `Audit\EventMap`. Users
  who previously hard-coded class-constant lists should migrate to string
  FQCNs or rely on the curated defaults.
* **BREAKING – config keys.** Added `audit_logs.use_curated_defaults`
  (default `true`). Set to `false` to opt out of curated defaults and listen
  only to `audit_logs.events`.
* **BREAKING – config keys.** Added env driver `LOGBOOK_AUDIT_EVENTS` and
  `LOGBOOK_AUDIT_EXCLUDE_EVENTS` for supplying comma-separated lists.
* **`LogbookServiceProvider`**: boot logic moved from `boot()` to the
  Statamic-preferred `bootAddon()` hook. Dropped static "already booted"
  flags in favour of container singletons and `Event::hasListeners(...)`
  where idempotency matters.
* Minimum `statamic/cms` raised to `^4.0|^5.0|^6.0`. Statamic 3 users must
  use the `1.x` LTS branch.
* Minimum `illuminate/support` and `illuminate/database` raised to
  `^9.0|^10.0|^11.0|^12.0`.
* PHP constraint widened to `^8.1|^8.2|^8.3|^8.4`.
* PHPUnit constraint widened to `^10.0|^11.0` to match the testbench matrix.

### Fixed

* **Statamic 6 boot regression.** Removed the eager
  `$this->app->bind('statamic.widgets', …)` rebind in
  `LogbookServiceProvider::register()` that clobbered
  `Statamic\Providers\ExtensionServiceProvider::registerBindingAlias()` and
  broke widget registration on Statamic 6.
* **Cross-major class-not-found fatals.** All Statamic event references are
  now string FQCNs filtered through `class_exists()` before `Event::listen()`
  so majors that have removed or renamed an event class never produce a
  fatal at addon boot.
* **`StatamicAuditSubscriber::isEntryEvent()`**: no longer hard-imports
  `\Statamic\Entries\Entry` at the top of the file, which previously forced
  autoload of a class that may not exist in every supported major. The
  check is now `class_exists()`-gated with a duck-typed fallback.

### Removed

* Removed the eager `statamic.widgets` container binding override. The
  widget registry is now left to core on Statamic 6 and shimmed only when a
  handle is missing.

### Security

* Continued strict filtering of event class names before `Event::listen()`
  to avoid untrusted class-loading from the config file.
* Spool ingestion continues to use `LOCK_EX` on write paths (unchanged).

### Upgrade notes

* Users upgrading from `1.x` should:
  1. Remove any explicit `\Statamic\Events\…::class` entries from
     `config/logbook.php`. The curated defaults now cover Statamic 3–6.
  2. If you relied on the old automatic discovery, set
     `LOGBOOK_AUDIT_DISCOVER_EVENTS=true` (still supported).
  3. If you pinned `statamic/cms: ^3.0`, stay on the `1.x` LTS branch.

---

## [1.5.1] – 2026-04-15

### Added

* Addon-level scheduler registration for `logbook:flush-spool` when spool ingest mode is enabled.

### Changed

* Added env-configurable flush interval (`LOGBOOK_SCHEDULER_FLUSH_SPOOL_EVERY_MINUTES`) with default hourly behavior.
* Added env toggles for scheduler enablement and overlap protection.

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

