Statamic Logbook

[![Latest Release](https://img.shields.io/github/v/release/emran-alhaddad/statamic-logbook?sort=semver)](https://github.com/emran-alhaddad/statamic-logbook/releases)
[![License](https://img.shields.io/github/license/emran-alhaddad/statamic-logbook)](LICENSE)
[![Open Issues](https://img.shields.io/github/issues/emran-alhaddad/statamic-logbook)](https://github.com/emran-alhaddad/statamic-logbook/issues)
[![Last Commit](https://img.shields.io/github/last-commit/emran-alhaddad/statamic-logbook)](https://github.com/emran-alhaddad/statamic-logbook/commits/master)

A production-ready logging and audit trail addon for Statamic.

Statamic Logbook provides a centralized place to review:

- System logs (Laravel / Monolog)
- User audit logs (who changed what, and when)

All inside the Statamic Control Panel, with filtering, analytics, and CSV export.

---

## Features

### System logs

- Captures Laravel log events automatically (no manual `logging.php` wiring required)
- Stores structured records in Logbook DB tables
- Captures request context (URL, method, IP, user, request id)
- Supports noise filtering by channel/message fragment

### Audit logs

- Captures high-signal Statamic mutation events by default
- Stores action, subject metadata, and entry-level before/after diffs
- Supports field-level ignore rules and value truncation
- Supports optional broader event discovery mode

### Control Panel

- Native Statamic CP styling/components
- Dashboard widgets (overview, trends, live pulse)
- Utility views with filtering and CSV export
- Widget set includes:
  - Logbook Overview (24h health cards)
  - Logbook Trends (daily stacked volume)
  - Logbook Pulse (live mixed feed + quick filters)

### Widget preview

#### Overview cards

![Logbook Overview Cards](docs/images/widgets/widget-overview-cards.png)

#### Trends

![Logbook Trends Volume](docs/images/widgets/widget-trends-volume.png)

#### Live pulse

![Logbook Live Pulse](docs/images/widgets/widget-live-pulse.png)

### Widget slugs (handles)

Use these widget handles when configuring dashboard widgets:

- `logbook_stats` (Overview cards)
- `logbook_trends` (Volume by day)
- `logbook_pulse` (Live feed)

---

## Compatibility

| Component | Supported |
| --------- | --------- |
| Statamic  | v4, v5, v6 |
| Laravel   | 10, 11 |
| PHP       | 8.1+ |

---

## Installation

```bash
composer require emran-alhaddad/statamic-logbook
php artisan vendor:publish --tag=logbook-config
```

---

## Setup (Required)

### 1) Configure Logbook database credentials in `.env`

These are required for Logbook to work:

```env
LOGBOOK_DB_CONNECTION=mysql
LOGBOOK_DB_HOST=127.0.0.1
LOGBOOK_DB_PORT=3306
LOGBOOK_DB_DATABASE=logbook_database
LOGBOOK_DB_USERNAME=logbook_user
LOGBOOK_DB_PASSWORD=secret
```

Then clear config cache:

```bash
php artisan config:clear
```

### 2) Install database tables

```bash
php artisan logbook:install
```

---

## Environment Variables

All variables used by the addon:

```env
# Required DB connection
LOGBOOK_DB_CONNECTION=mysql
LOGBOOK_DB_HOST=127.0.0.1
LOGBOOK_DB_PORT=3306
LOGBOOK_DB_DATABASE=logbook_database
LOGBOOK_DB_USERNAME=logbook_user
LOGBOOK_DB_PASSWORD=secret

# Optional DB tuning
LOGBOOK_DB_SOCKET=
LOGBOOK_DB_CHARSET=utf8mb4
LOGBOOK_DB_COLLATION=utf8mb4_unicode_ci

# System logging
LOGBOOK_SYSTEM_LOGS_ENABLED=true
LOGBOOK_SYSTEM_LOGS_LEVEL=debug
LOGBOOK_SYSTEM_LOGS_BUBBLE=true
LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS=deprecations
LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES=Since symfony/http-foundation,Unable to create configured logger. Using emergency logger.

# Audit logging
LOGBOOK_AUDIT_DISCOVER_EVENTS=false
LOGBOOK_AUDIT_EXCLUDE_EVENTS=
LOGBOOK_AUDIT_IGNORE_FIELDS=updated_at,created_at,date,uri,slug
LOGBOOK_AUDIT_MAX_VALUE_LENGTH=2000

# Retention
LOGBOOK_RETENTION_DAYS=365

# Ingestion mode
LOGBOOK_INGEST_MODE=sync
LOGBOOK_SPOOL_PATH=storage/app/logbook/spool
LOGBOOK_SPOOL_MAX_MB=256
LOGBOOK_SPOOL_BACKPRESSURE=drop_oldest
```

### Short `.env` example (minimal working setup)

```env
LOGBOOK_DB_CONNECTION=mysql
LOGBOOK_DB_HOST=127.0.0.1
LOGBOOK_DB_PORT=3306
LOGBOOK_DB_DATABASE=logbook_database
LOGBOOK_DB_USERNAME=logbook_user
LOGBOOK_DB_PASSWORD=secret

LOGBOOK_INGEST_MODE=spool
LOGBOOK_SPOOL_PATH=storage/app/logbook/spool
```

### Required variables

- `LOGBOOK_DB_CONNECTION`
- `LOGBOOK_DB_HOST`
- `LOGBOOK_DB_PORT`
- `LOGBOOK_DB_DATABASE`
- `LOGBOOK_DB_USERNAME`
- `LOGBOOK_DB_PASSWORD`

### Optional variables and behavior

- `LOGBOOK_DB_SOCKET`: unix socket path.
- `LOGBOOK_DB_CHARSET`: DB charset (default `utf8mb4`).
- `LOGBOOK_DB_COLLATION`: DB collation (default `utf8mb4_unicode_ci`).
- `LOGBOOK_SYSTEM_LOGS_ENABLED`: enable/disable system log capture (default `true`).
- `LOGBOOK_SYSTEM_LOGS_LEVEL`: minimum system level (default `debug`).
- `LOGBOOK_SYSTEM_LOGS_BUBBLE`: Monolog bubble behavior (default `true`).
- `LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS`: comma-separated ignored channels.
- `LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES`: comma-separated ignored message fragments.
- `LOGBOOK_AUDIT_DISCOVER_EVENTS`: when `true`, merges discovered Statamic events with curated defaults.
- `LOGBOOK_AUDIT_EXCLUDE_EVENTS`: comma-separated audit event classes to exclude.
- `LOGBOOK_AUDIT_IGNORE_FIELDS`: comma-separated fields ignored in diffs.
- `LOGBOOK_AUDIT_MAX_VALUE_LENGTH`: max stored value length before truncation.
- `LOGBOOK_RETENTION_DAYS`: retention period for prune command.
- `LOGBOOK_INGEST_MODE`: `sync` (direct DB) or `spool` (local file spool + background flush).
- `LOGBOOK_SPOOL_PATH`: spool directory path.
- `LOGBOOK_SPOOL_MAX_MB`: max spool size before backpressure policy applies.
- `LOGBOOK_SPOOL_BACKPRESSURE`: currently supports `drop_oldest`.

---

## Ingestion Modes

### `sync` mode

- Writes system/audit rows directly to DB in request lifecycle.

### `spool` mode

- Writes NDJSON records to local spool files in request lifecycle.
- Flushes spool files to DB in background via command/scheduler.
- If enqueue fails, Logbook falls back to direct DB insert (prevents silent drops).

---

## Spool Flush and Background Scheduling

Flush command:

```bash
php artisan logbook:flush-spool
```

Common usage:

```bash
php artisan logbook:flush-spool --type=all --limit=1000
php artisan logbook:flush-spool --type=system --dry-run
```

Command output includes:

- queued files (before/after)
- queued bytes (before/after)
- failed files (before/after)
- failure reason and failed-file destination when flush fails

### Laravel scheduler entry (app code)

Add to your app scheduler (`routes/console.php` or `Console\Kernel`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('logbook:flush-spool --type=all --limit=1000')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

Short scheduler example:

```php
Schedule::command('logbook:flush-spool')->everyFiveMinutes();
```

### OS cron (host level, required)

```bash
* * * * * cd /absolute/path/to/your-laravel-app && php artisan schedule:run >> /dev/null 2>&1
```

Short cron example:

```bash
* * * * * php /absolute/path/to/your-laravel-app/artisan schedule:run >> /dev/null 2>&1
```

---

## Operational Commands

- Install tables: `php artisan logbook:install`
- Prune old rows: `php artisan logbook:prune`
- Flush spool: `php artisan logbook:flush-spool`

### Run maintenance from Control Panel

From `Utilities -> Logbook`, use the header action buttons:

- `Prune Logs`: executes `php artisan logbook:prune`
- `Flush Spool`: executes `php artisan logbook:flush-spool`

Each action shows a CP toast status lifecycle:

- `in-progress` when started
- `done` on success
- `failed` on command/transport error

Implementation note: CP action requests are submitted as form-encoded POST with `_token` to satisfy Laravel/Statamic CSRF validation.

---

## Quick Verification

1. Set required DB env vars.
2. Run `php artisan config:clear`.
3. Run `php artisan logbook:install`.
4. Trigger a test log:
   ```php
   \Log::error('logbook smoke test', ['source' => 'manual-check']);
   ```
5. If in spool mode, run `php artisan logbook:flush-spool --type=all`.
6. Confirm rows appear in CP (System Logs / Audit Logs).

---

## Test Coverage

This repository includes a lightweight PHPUnit suite focused on regression checks for critical behavior:

- Audit action normalization mapping
- Curated audit default mode (`discover_events=false`)
- Pulse widget filter listener singleton guard

Run tests:

```bash
./vendor/bin/phpunit --configuration phpunit.xml
```

---

## What To Do / What Not To Do

### Do

- Use a dedicated DB/schema for Logbook where possible.
- Keep scheduler and cron configured if using `spool` mode.
- Keep `LOGBOOK_AUDIT_DISCOVER_EVENTS=false` unless you need wider coverage.
- Monitor failed spool files under `storage/app/logbook/spool/failed/`.

### Do not

- Do not commit real credentials.
- Do not disable scheduler while using `spool` mode.
- Do not point Logbook to an uncontrolled DB.
- Do not treat audit logs as editable content.

---

## Troubleshooting

- If spool files are not created:
  - run `php artisan config:clear`
  - verify `LOGBOOK_INGEST_MODE=spool`
  - verify spool directory write permissions for PHP-FPM user
- If flush fails:
  - read printed `Flush error:` message
  - inspect `storage/app/logbook/spool/failed/...`
  - fix root cause, requeue failed file, and run flush again

---

## Release and History

Known tags:

- `v1.5.0` (current)
- `v1.4.0`
- `v1.3.1`
- `v1.3.0`
- `v1.2.0`
- `v1.1.0`
- `v1.0.0`

Recent changes after `v1.2.0` are documented under `CHANGELOG.md` -> `Unreleased`.

### Current release

- Current release: `v1.5.0`
- Focus: CSRF compatibility hardening for CP maintenance CTAs using form-encoded POST + `_token`.

---

## License

MIT License. See `LICENSE`.

## Author

Built and maintained by Emran Alhaddad  
GitHub: <https://github.com/emran-alhaddad>

## Changelog

See `CHANGELOG.md` for release history.
