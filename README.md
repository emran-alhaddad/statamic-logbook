# Statamic Logbook

[Latest Release](https://github.com/emran-alhaddad/statamic-logbook/releases)
[License](LICENSE)
[Open Issues](https://github.com/emran-alhaddad/statamic-logbook/issues)
[Last Commit](https://github.com/emran-alhaddad/statamic-logbook/commits/master)

A production-ready logging and audit trail addon for Statamic.

Statamic Logbook provides a centralized place to review:

- System logs (Laravel / Monolog)
- User audit logs (who changed what, and when)

All inside the Statamic Control Panel, with filtering, analytics, and CSV export.

---

## Quick start

Four steps, about five minutes. Everything else in this README is optional.

**1. Install the package.**

```bash
composer require emran-alhaddad/statamic-logbook
```

**2. Open the Control Panel** and go to **Utilities → Logbook**.

You get a setup screen already filled in with your application's own database
details. Press **Connect & install**. Logbook creates its own tables and starts
capturing straight away.

**3. Write one log line**, from a route, a tinker session, or anywhere else:

```php
\Log::error('logbook smoke test', ['source' => 'quick-start']);
```

**4. Reload Utilities → Logbook.** Your line is on the **System Logs** tab, and
whatever you last saved in the CP is on **Audit Logs**.

That is the whole setup. Nothing to schedule, no `config/logging.php` to wire
up, no migrations to run.

| If you want to… | Go to |
| --- | --- |
| Install from the command line instead | [From the command line](#from-the-command-line) |
| Change what gets captured | [Configuration](#configuration) |
| Keep logs in their own database | [Required: database credentials](#required-database-credentials) |
| Stop capturing a noisy log channel | [Which log channel a row belongs to](#which-log-channel-a-row-belongs-to) |
| Handle high log volume | [Ingestion Modes](#ingestion-modes) |
| Upgrade an existing install | [UPGRADE.md](UPGRADE.md) |

---

## Compatibility

| Component | Supported            |
| --------- | -------------------- |
| Statamic  | v4, v5, v6           |
| Laravel   | 9, 10, 11, 12, 13    |
| PHP       | 8.1, 8.2, 8.3, 8.4   |

Statamic 3 users stay on the dedicated [`1.x` LTS branch](https://github.com/emran-alhaddad/statamic-logbook/tree/1.x).

---

## Installation

```bash
composer require emran-alhaddad/statamic-logbook
```

That is the whole install. Logbook registers itself, adds **Utilities →
Logbook** to the navigation, and publishes its Control Panel stylesheet and
script on the first CP request. Next step is [Setup](#setup), where you point
it at a database.

### Publishing by hand

You do not normally need this. Logbook keeps `public/vendor/statamic-logbook/`
in step with the bundles inside the package by itself, copying anything missing
or stale on the first Control Panel request after an install or upgrade — so
the CP is never left unstyled because a publish step was skipped.

Publish by hand if you want `config/logbook.php` in your repository, or if you
deploy from a read-only `public/`. Three tags are available:

| Tag | Publishes |
| --- | --- |
| `logbook` | config + CP stylesheet + CP script |
| `logbook-assets` | CP stylesheet + CP script only |
| `logbook-config` | `config/logbook.php` only |

```bash
php artisan vendor:publish --tag=logbook
```

On an immutable deploy, publish during the build and turn the runtime copy off:

```bash
php artisan vendor:publish --tag=logbook-assets --force
```

```env
LOGBOOK_ASSETS_AUTO_PUBLISH=false
```

---

## Setup

Logbook stores its rows in a database you nominate, which is the one thing it
cannot decide for you. Pick **one** of the two routes below — they produce the
same result.

### From the Control Panel (recommended)

Open **Utilities → Logbook**. On a fresh install you get a setup screen
prefilled from `LOGBOOK_DB_*` if you have set them, and from your application's
own database connection if you have not:

1. Confirm or edit the database details.
2. Press **Connect & install**.

Logbook creates its tables and starts capturing. The details you confirm are
written to your `.env` for you, so you never have to type them by hand.
Everything after this point is configured on the **Settings** tab.

So by default Logbook logs into your application's own database. Point it at a
separate database if you would rather keep audit history isolated.

### From the command line

For a scripted deploy, or if you would rather not click through the CP. Set the
credentials in `.env`:

```env
LOGBOOK_DB_CONNECTION=mysql
LOGBOOK_DB_HOST=127.0.0.1
LOGBOOK_DB_PORT=3306
LOGBOOK_DB_DATABASE=logbook_database
LOGBOOK_DB_USERNAME=logbook_user
LOGBOOK_DB_PASSWORD=secret
```

then:

```bash
php artisan config:clear
php artisan logbook:install
```

To log into your application's own database, reuse its credentials above — or
set `LOGBOOK_DB_CONNECTION` to the name of a connection that already exists in
`config/database.php` and leave the rest blank.

`logbook:install` is idempotent, so it is safe to leave in a deploy script. On
MySQL/MariaDB it creates the database as well, if the user has
`CREATE DATABASE`.

### Upgrading an existing install

Already running Logbook? See **[UPGRADE.md](UPGRADE.md)**. Short version: your
log rows are untouched, and `php artisan logbook:upgrade` adds any tables
introduced since your version and imports your existing `.env` configuration
into the settings screen.

> **Which wins, `.env` or the Control Panel?** Settings you save in the CP are
> stored in the `logbook_settings` table and take precedence over `LOGBOOK_*`
> env values, which remain the defaults. So editing `.env` on a configured
> install will look like it does nothing. To hand control back to `.env`,
> delete the `settings` row from `logbook_settings`.

---

## Check it is working

With setup finished, four things to try:

**1. Write a system-log row.**

```php
\Log::error('logbook smoke test', ['source' => 'manual-check']);
```

**2. Write an audit row** by saving any entry, term, or user in the CP.

**3. If you run `spool` mode**, flush the spool so the rows reach the database:

```bash
php artisan logbook:flush-spool --type=all
```

**4. Open Utilities → Logbook** and confirm both rows are there — the log line
on **System Logs**, the save on **Audit Logs**.

Prefer to check from the database directly:

```sql
SELECT level, channel, message, created_at
FROM logbook_system_logs
ORDER BY id DESC
LIMIT 5;
```

**Seeing nothing?** Check **Settings** first — a stream whose master switch is
off captures nothing and hides its own tab. Then see
[Troubleshooting](#troubleshooting).

---

## Configuration

Logbook has two configuration surfaces, and it matters which one owns what:

| Surface | Owns |
| --- | --- |
| **`.env`** | Database credentials (always), plus advanced knobs with no CP equivalent |
| **CP → Settings** | Day-to-day capture switches: streams, levels, per-event toggles, page views, retention |

On a configured install the CP wins. Editing `LOGBOOK_SYSTEM_LOGS_ENABLED` in
`.env` will look like it does nothing, because the saved settings override it.
Env values act as the **defaults** — and on upgrade they are imported into the
settings row once (see [UPGRADE.md](UPGRADE.md)). To hand control back to
`.env`, delete the `settings` row from `logbook_settings`.

### Required: database credentials

Always read from `.env` — these are never CP-managed, since Logbook needs them
before it can read its own settings table. Point them at your app's database to
log there, or at a separate database to keep audit history isolated.

```env
LOGBOOK_DB_CONNECTION=mysql
LOGBOOK_DB_HOST=127.0.0.1
LOGBOOK_DB_PORT=3306
LOGBOOK_DB_DATABASE=logbook_database
LOGBOOK_DB_USERNAME=logbook_user
LOGBOOK_DB_PASSWORD=secret
```

Optional tuning: `LOGBOOK_DB_SOCKET` (unix socket), `LOGBOOK_DB_CHARSET`
(`utf8mb4`), `LOGBOOK_DB_COLLATION` (`utf8mb4_unicode_ci`).

If you complete setup from the Control Panel these are written to `.env` for
you, so you usually never type them by hand.

### Managed in the Control Panel

Set these on the Settings screen. The env vars below are only the initial
defaults; once settings are saved, the CP value wins.

| Variable | Default | Settings screen |
| --- | --- | --- |
| `LOGBOOK_SYSTEM_LOGS_ENABLED` | `true` | System Logs master switch |
| `LOGBOOK_SYSTEM_LOGS_LEVEL` | `debug` | Per-level capture switches |
| `LOGBOOK_AUDIT_LOGS_ENABLED` | `true` | Audit Logs master switch |
| `LOGBOOK_AUDIT_EXCLUDE_EVENTS` | *(empty)* | Per-event toggles |
| `LOGBOOK_RETENTION_DAYS` | `365` | Keep logs for … |
| `LOGBOOK_ACTIVITY_ENABLED` | `false` | Page Views master switch |
| `LOGBOOK_ACTIVITY_DEDUPE_MINUTES` | `5` | Ignore repeat views within … |
| `LOGBOOK_ACTIVITY_EXCLUDED_ROLES` | *(empty)* | Who gets tracked (`__super` = super admins) |
| `LOGBOOK_ACTIVITY_RETENTION_DAYS` | `30` | Keep view rows for … |

### Env-only (no CP equivalent)

```env
# Control Panel assets
LOGBOOK_ASSETS_AUTO_PUBLISH=true

# System log noise filters
LOGBOOK_SYSTEM_LOGS_BUBBLE=true
LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS=deprecations
LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES=Since symfony/http-foundation

# Audit event resolution and diffing
LOGBOOK_AUDIT_USE_CURATED_DEFAULTS=true
LOGBOOK_AUDIT_DISCOVER_EVENTS=false
LOGBOOK_AUDIT_EVENTS=
LOGBOOK_AUDIT_AUTH_EVENTS=true
LOGBOOK_AUDIT_IGNORE_FIELDS=updated_at,created_at,date,uri,slug
LOGBOOK_AUDIT_MAX_VALUE_LENGTH=2000

# Ingestion (see "Ingestion Modes" below)
LOGBOOK_INGEST_MODE=sync
LOGBOOK_SPOOL_PATH=storage/app/logbook/spool
LOGBOOK_SPOOL_MAX_MB=256
LOGBOOK_SPOOL_BACKPRESSURE=drop_oldest

# Addon scheduler for the spool flush
LOGBOOK_SCHEDULER_FLUSH_SPOOL_ENABLED=true
LOGBOOK_SCHEDULER_FLUSH_SPOOL_EVERY_MINUTES=60
LOGBOOK_SCHEDULER_FLUSH_SPOOL_WITHOUT_OVERLAPPING=true
```

- `LOGBOOK_ASSETS_AUTO_PUBLISH` — set `false` to stop Logbook copying its CP
  bundles into `public/vendor/statamic-logbook/` at runtime. Publish them in
  your build step instead (`vendor:publish --tag=logbook-assets --force`).
- `LOGBOOK_SYSTEM_LOGS_BUBBLE` — Monolog bubble behaviour.
- `LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS` / `..._IGNORE_MESSAGES` — comma-separated
  channels and message fragments to drop. Anything you add in the CP's "Ignored
  channels" box is merged **on top of** these, so both keep applying. Channel
  names are the ones from your `config/logging.php` (`stack`, `single`,
  `deprecations`, …); ad-hoc loggers from `Log::build()` / `Log::stack()` carry
  no channel name of their own and are recorded under `logbook`.
- `LOGBOOK_AUDIT_USE_CURATED_DEFAULTS` — set `false` to listen only to the
  classes you list in `LOGBOOK_AUDIT_EVENTS`.
- `LOGBOOK_AUDIT_DISCOVER_EVENTS` — merge every discovered `Statamic\Events\*`
  class with the curated set. Verbose; off by default.
- `LOGBOOK_AUDIT_AUTH_EVENTS` — capture Laravel auth events (sign-in, sign-out,
  failed attempts, password resets).
- `LOGBOOK_AUDIT_IGNORE_FIELDS` — fields excluded from change diffs. Merged with
  the CP's "Ignored fields" box.
- `LOGBOOK_AUDIT_MAX_VALUE_LENGTH` — truncation ceiling for stored diff values.
- `LOGBOOK_INGEST_MODE` — `sync` (write during the request) or `spool` (local
  NDJSON spool + scheduled flush).

Redaction of secret-looking keys is configured in `config/logbook.php` under
`privacy`, not via env.

### Which log channel a row belongs to

Every system-log row records the channel it came from, using the names in your
`config/logging.php`:

```php
\Log::info('to the default channel');            // channel: stack
\Log::channel('single')->warning('to one file'); // channel: single
\Log::channel('slack')->error('to Slack');       // channel: slack
```

That is what `ignore_channels` matches on, so you can drop a noisy channel
without touching the code that writes to it:

```env
# Capture everything except deprecations and query logging
LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS=deprecations,queries
```

Two things worth knowing:

- **PHP deprecations are ignored by default.** Laravel routes them through
  `Log::channel('deprecations')`, which is in the shipped default above. Set
  `LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS=` (empty) if you want them recorded.
- **Ad-hoc loggers are recorded as `logbook`.** `Log::build([...])` and
  `Log::stack([...])` carry no channel name for Laravel to report, so their rows
  get the `logbook` fallback and `ignore_channels` cannot target them. They are
  still captured.

Before v2.3.0 every row was stored as `logbook` and `ignore_channels` matched
nothing — see
[#3](https://github.com/emran-alhaddad/statamic-logbook/issues/3).

---

## Features

### System logs

- Captures Laravel log events automatically (no manual `logging.php` wiring required)
- Stores structured records in Logbook DB tables
- Captures request context (URL, method, IP, user, request id)
- Supports noise filtering by channel/message fragment, using the real channel
  names from `config/logging.php`

### Audit logs

- Captures high-signal Statamic mutation events by default
- Stores action, subject metadata, and entry-level before/after diffs
- Supports field-level ignore rules and value truncation
- Supports optional broader event discovery mode

### Page views (optional)

- Records who opened which entry, collection, user, asset… across 29 CP routes
- Shown on the **Timeline only** (while tracking is on) — never mixed into Audit
  Logs, which stays a clean record of what changed
- Per-page switches, so you track only the surfaces you care about
- Collapses repeat opens of the same thing into one row (configurable window)
- Excludes chosen roles, or super admins, from being tracked
- Its own retention window, so read volume never inflates your change history
- Off by default

### Settings in the Control Panel

- Every capture switch lives at *Utilities → Logbook → Settings* — no `.env`
  edit, no `config:clear`, no deploy
- Master switch per stream, per-level switches for system logs, and per-event
  switches for audit logs grouped by component
- A switched-off event gets no listener at all: it is not captured, not merely
  hidden from the listing
- Shows real 7-day volume next to each level, component and tracked page, so
  you can see what a switch actually costs before flipping it
- `LOGBOOK_*` env values remain as defaults; CP settings take precedence

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

Logbook Overview Cards

#### Trends

Logbook Trends Volume

#### Live pulse

Logbook Live Pulse

### Widget slugs (handles)

Use these widget handles when configuring dashboard widgets:

- `logbook_stats` (Overview cards)
- `logbook_trends` (Volume by day + 7×24 heatmap)
- `logbook_pulse` (Live feed)

---

## Control Panel walkthrough

The utility lives under `Utilities → Logbook`. The same page hosts **System**, **Audit**, **Timeline** and **Settings** tabs. A stream you switch off in Settings loses its tab and its route returns 404, and it stops appearing on the Timeline. Every feature below works the same on Statamic 4, 5, and 6 — the addon ships its own stylesheet and script bundle so nothing depends on the host CP's Tailwind purge configuration.

### Dashboard widgets

- **Overview (`logbook_stats`)**: four KPI cards showing total system lines, errors, audit events, and the busiest hour in the last 24 h. Each card carries a sparkline, a period-over-period delta chip (`↑ +12.4%`, `↓ −3%`), a status pill, and — for errors — a "Last error 3h ago" chip plus a "Top error signatures · 24h" panel that groups similar errors by a normalised fingerprint.
- **Trends (`logbook_trends`)**: daily stacked bars for the last 14 days followed by a 7×24 channel heatmap so you can spot what hour of what day is loudest at a glance.
- **Pulse (`logbook_pulse`)**: live mixed feed of system + audit events with quick-filter pills (System · Audit · Errors · Warnings).

### Utility page features

**Filtering & search.** Each tab has a sticky filter bar with date-range pickers, level / channel / action / subject dropdowns, and a full-text search on message / user / subject. Filters compose with the URL so they're shareable; empty fields are stripped so `?level=error` stays clean.

**Sortable columns.** Click any column header to sort. `sort` and `dir` are whitelisted server-side per table.

**Per-page + pagination.** A footer chip-style paginator (Prev · 1 · 2 · … · Next) with a `[25 | 50 | 100 | 200]` per-page dropdown. The selector preserves every other query param.

**Saved filter presets.** A `Presets ▾` button on System + Audit tabs lets you snapshot the current filter URL under a name. Presets are stored per-tab in `localStorage` under `statamic-logbook.presets.<scope>`. Opening a preset restores the full filter state.

**Live tail.** A pulsing toggle next to `Export CSV` polls a JSON endpoint every few seconds. When new rows land, the label becomes "N new · click to refresh". The poll automatically:

- pauses when the tab is hidden (`visibilitychange`) or offline,
- resumes on `online` / visible,
- backs off exponentially on consecutive errors (5 s → 10 s → 20 s → 40 s, capped at 60 s with jitter),
- relaxes toward the upper bound when the server returns no new rows for several ticks,
- cleans up on `pagehide` / `beforeunload`.

**Density toggle.** `Compact · Cozy · Spacious` in the toolbar. This is not a font-size switch: Compact hides secondary meta rows, forces single-line truncation, and shrinks chips + the filter grid; Cozy is the default; Spacious releases the cell clamp, lets long messages wrap, and enlarges the toolbar. The preference is persisted under `statamic-logbook.density` and synced across devices when preferences are linked to your CP user (see *User preferences* below).

**Cell truncation + JSON viewer.** Long messages, user ids / emails, action strings and subject titles are clamped to a single line. Every row has a `JSON` action that opens the full record as pretty-printed JSON in a modal (copy-to-clipboard in two clicks). The full value is never lost.

**Human-readable audit actions.** Raw event strings like `statamic.user.saved` are shown as `User updated` via an `AuditActionPresenter`. On `update` events, the row carries an inline ribbon with a truncated "from → to" summary of the first 1–2 changed fields (e.g. `title: "Old" → "New"`) using the existing `changes` column. Zero schema changes; the raw event name stays on disk so `?action=statamic.user.saved` keeps working.

**Unified timeline.** The `Timeline` tab interleaves system + audit events into a single chronological rail grouped by day (`Today` / `Yesterday` / explicit dates). It is also the only place page views appear, and only while page-view tracking is on. Filter by stream (system / audit); the severity pills (error / warn / info) narrow the system stream only, since audit events have no log level.

**CSV export.** The `Export CSV` button downloads the currently-filtered rows as a CSV respecting all filters + sort.

### Keyboard shortcuts

- `/` — focus the search input on the current tab.
- `g s` — go to System logs.
- `g a` — go to Audit logs.

Shortcuts are suppressed while typing in form fields.

---

## User preferences

`logbook:install` creates `logbook_user_prefs` in the **logbook database** (not the project database). One row per CP user, a single JSON `prefs` blob. The UI uses `localStorage` as a zero-config fallback for density / saved presets / per-page default; when the preferences table is available, a set of CP endpoints allows those values to sync across devices:

- `GET    /cp/utilities/logbook/prefs`          — return every pref for the current user
- `GET    /cp/utilities/logbook/prefs/{key}`    — return one pref
- `PUT    /cp/utilities/logbook/prefs/{key}`    — set one pref (body: `{ "value": ... }`)
- `DELETE /cp/utilities/logbook/prefs/{key}`    — remove one pref

All four endpoints are gated by `can:view logbook` and fail soft — if the table is missing (pre-upgrade install) or the logbook DB is unreachable, the UI continues to use `localStorage` and no error is surfaced to the user. See `src/Support/UserPrefsRepository.php` for the storage contract and rationale for living in the logbook DB rather than the project DB (self-contained addon, clean uninstall by dropping the logbook DB, respects teams that deliberately separate logs from prod).

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

### Built-in addon scheduler (spool mode)

When `LOGBOOK_INGEST_MODE=spool`, the addon auto-registers `logbook:flush-spool` in Laravel Scheduler.

Default behavior:

- Runs every 60 minutes
- Uses `withoutOverlapping()` by default
- Can be tuned via:
  - `LOGBOOK_SCHEDULER_FLUSH_SPOOL_ENABLED`
  - `LOGBOOK_SCHEDULER_FLUSH_SPOOL_EVERY_MINUTES`
  - `LOGBOOK_SCHEDULER_FLUSH_SPOOL_WITHOUT_OVERLAPPING`

Important:

- This scheduler is only active in `spool` mode.
- `logbook:prune` is not auto-scheduled by the addon.

### Application-level scheduler entry (optional override)

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
- Upgrade an existing install: `php artisan logbook:upgrade` — adds tables
  introduced since your installed version and imports `.env` configuration into
  the CP settings. Idempotent; `--force` re-imports over existing settings.
  See [UPGRADE.md](UPGRADE.md).
- Prune old rows: `php artisan logbook:prune` — honours both retention windows
  (system/audit rows, and page views separately). `--dry-run` reports what it
  would delete without touching anything; `--days=90` overrides the configured
  retention window for one run.
- Flush spool: `php artisan logbook:flush-spool` — `--type=system|audit|all`
  (default `all`), `--limit=1000` records per file pass, and `--dry-run`.

```bash
# See what a prune would remove, without removing it
php artisan logbook:prune --dry-run
```

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

## What To Do / What Not To Do

### Do

- Use a dedicated DB/schema for Logbook where possible.
- Keep scheduler and cron configured if using `spool` mode.
- Keep `LOGBOOK_AUDIT_DISCOVER_EVENTS=false` unless you need wider coverage.
- Monitor failed spool files under `storage/app/logbook/spool/failed/`.
- Run `php artisan logbook:upgrade` as part of deploys that bump the addon.
- Switch off event groups you do not need, rather than pruning harder — a
  disabled event is never written in the first place.
- Give page views a shorter retention than the audit trail; they are the
  highest-volume thing Logbook records.

### Do not

- Do not commit real credentials.
- Do not expect `.env` changes to take effect on a configured install — the CP
  settings override them.
- Do not disable scheduler while using `spool` mode.
- Do not point Logbook to an uncontrolled DB.
- Do not treat audit logs as editable content.

---

## Troubleshooting

- **Spool files are not created**:
  - run `php artisan config:clear`
  - verify `LOGBOOK_INGEST_MODE=spool`
  - verify spool directory write permissions for the PHP-FPM user
- **Flush fails**:
  - read the `Flush error:` line from command output
  - look under `storage/app/logbook/spool/failed/` for a file named like `20240101_12.ndjson.20250122093015.failed` — the CP UI reports only the basename on purpose, never the absolute path
  - fix the root cause, requeue the failed file back into `spool/<type>/`, and run `php artisan logbook:flush-spool` again
- **"Unknown database" on install**: `php artisan logbook:install` auto-creates the database on MySQL/MariaDB if the configured DB user has `CREATE DATABASE`. If your user doesn't, create the DB manually (`CREATE DATABASE logbook_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`) and re-run install.
- **CP widgets render as unstyled gray boxes on Statamic 6**: Logbook republishes its bundles by itself on the first CP request, so this should fix itself on reload. If `public/` is read-only (or `LOGBOOK_ASSETS_AUTO_PUBLISH=false`), run `php artisan vendor:publish --tag=logbook-assets --force` to refresh the published stylesheet + script, then clear the browser cache.
- **Pulse widget filter pills ignore clicks on Statamic 6**: symptom of a stale `statamic-logbook.min.js` publish; re-run the vendor:publish line above.
- **"Presets ▾" dropdown doesn't appear**: known fix in v2.0.0 (the button now portals its menu into `<body>` to escape the filter toolbar's `backdrop-filter` containing block); upgrade to v2.0.0+.

---

## Test Coverage

This repository includes a PHPUnit suite (148 tests) focused on regression
checks for critical behavior:

- `LogChannelTapTest` — the channel name reaches `MessageLogged`, and the marker carrying it never reaches a Monolog handler, so it cannot leak into the host's own log files
- `SystemLogChannelTest` — a tap is attached to every configured channel without duplicating the host's own taps, the `deprecations` channel is materialised before Laravel gets to it, and `ignore_channels` filters on what the tap reports
- `PublishTagsTest` — the `logbook`, `logbook-assets` and `logbook-config` tags resolve to the right files, and the config is kept out of the slug tag Statamic force-publishes
- `AssetPublisherTest` — staleness rules, the once-per-process guard, the opt-out, and that a read-only `public/` never throws
- `EventMapTest` — per-major event resolution, silent filtering of missing event classes, exclusion semantics
- `StatamicAuditSubscriberResolutionTest` — cross-major class-not-found safety, exclude-list round-trip, and that a **disabled event gets no recording listener** (not captured, not merely hidden)
- `SettingsRepositoryTest` — sanitisation, type coercion, out-of-range fallbacks, config overlay
- `UpgradeImportTest` — 2.0.x `.env` configuration imports correctly, and the old level threshold becomes per-level switches without inflating log volume
- `LogCpPageViewsTest` — per-page and role gating, dedupe window, and that Inertia CP navigations count as page views while data XHRs and partial reloads do not
- `ComponentPaletteTest` — every event group, tracked page and subject type has a colour and icon, and no two verbs share an icon
- `TimelineFiltersTest` — query parameters coerced to scalars (array input must not 500), and `'audit'` is never treated as a severity
- `WidgetRegistryShimTest` — capability-gated shim firing only when core registration is absent, idempotency
- Audit action normalization mapping
- Curated audit default mode (`discover_events=false`)
- Pulse widget filter listener singleton guard

Run tests:

```bash
composer test
```

### Rebuilding the CP bundles

The addon ships pre-minified `statamic-logbook.min.css` and `statamic-logbook.min.js` in `resources/dist/`. To rebuild from the source files in the same directory:

```bash
npm install
npm run build
```

---

## Release and History

Known tags:

- `v2.3.1` (current) — documentation only: the README now reads in install order
- `v2.3.0` — real log-channel names, working `ignore_channels`, and CP assets that publish themselves
- `v2.2.0` — page views off the Audit Logs page
- `v2.1.0` — CP-managed settings, page-view tracking
- `v2.0.2`
- `v2.0.1`
- `v2.0.0` — Statamic 6 support + CP redesign + UX polish pass
- `v1.5.2`
- `v1.5.1`
- `v1.5.0`
- `v1.4.0`
- `v1.3.1`
- `v1.3.0`
- `v1.2.0`
- `v1.1.0`
- `v1.0.0`

Statamic 3 users continue on the `1.x` LTS branch. See `CHANGELOG.md` for the full per-version history.

### The v2 line

The v2 release targets three things at once:

1. **First-class Statamic 6 support.** The core widget registry binding conflict that broke the dashboard on Statamic 6 is gone. Event references are string FQCNs filtered through `class_exists()` so missing-event-class fatals across majors never happen. `Audit\EventMap` ships a curated per-major event registry (majors 3–6) that returns only the event classes that exist on the running major.
2. **Self-contained CP surface.** The addon ships its own stylesheet (`resources/dist/statamic-logbook.min.css`) and script bundle (`resources/dist/statamic-logbook.min.js`) registered via Statamic's `$stylesheets` / `$scripts`. Rendering is independent of the host CP's Tailwind purge configuration, and the script runs outside the Vue-compiled widget subtree so Statamic 6's `DynamicHtmlRenderer` can't strip it.
3. **Deep CP UX pass.** Sortable columns, density toggle, saved filter presets, live tail with adaptive polling, keyboard shortcuts, unified timeline, per-page selector, chip-style paginator, error fingerprint grouping, 7×24 channel heatmap, humanised audit actions with inline "from → to" ribbons, cross-device user preferences table, and more. See `CHANGELOG.md` for the full list.

---

## License

MIT License. See `LICENSE`.

---

## Author

Built and maintained by Emran Alhaddad  
GitHub: [https://github.com/emran-alhaddad](https://github.com/emran-alhaddad)

---

## Changelog

See `CHANGELOG.md` for release history.
