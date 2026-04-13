Statamic Logbook

A production-ready **logging and audit trail addon** for Statamic.

**Statamic Logbook** provides a centralized place to review:

- 📘 **System logs** (Laravel / Monolog)
- 📝 **User audit logs** (who changed what, and when)

All directly inside the **Statamic Control Panel**, with filtering, analytics, and CSV export.

---

## ✨ Features

### System Logs

- Stores application logs directly in the database
- Captures request context (URL, method, IP, user)
- Filter by date, level, and message
- CSV export
- Works automatically after install (no `logging.php` wiring required)

### Audit Logs

- Tracks user actions across Statamic
- Records **what changed → from → to**
- Supports entries, taxonomies, globals, navs, and nav trees
- Field-level ignore rules
- Safe truncation for large values
- CSV export

### Control Panel

- Native Statamic CP UI
- Fast filtering & pagination
- Modal previews for context & changes
- Dashboard widgets for overview, trends, and live pulse
- Uses Statamic CP classes directly (no frontend build pipeline)

---

## 📦 Installation

```bash
composer require emran-alhaddad/statamic-logbook
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=logbook-config
```

---

## 🗄 Database Configuration (Required)

Statamic Logbook **requires** a database connection defined via `.env`.
If these variables are missing, the addon **will not function**.

Add the following to your `.env` file:

```env
LOGBOOK_DB_CONNECTION=mysql
LOGBOOK_DB_HOST=127.0.0.1
LOGBOOK_DB_PORT=3306
LOGBOOK_DB_DATABASE=logbook_database
LOGBOOK_DB_USERNAME=logbook_user
LOGBOOK_DB_PASSWORD=secret
```

### ✅ What to do

- Create a dedicated database (example: `logbook_database`)
- Create a database user with full access to that database
- Add the variables above to `.env`
- Clear configuration cache:

  ```bash
  php artisan config:clear
  ```

### ❌ What NOT to do

- ❌ Do not use dots (`.`) in database names — use underscores (`_`)
- ❌ Do not reuse credentials from unrelated systems
- ❌ Do not point Logbook to a database you do not fully control
- ❌ Do not commit real credentials to version control

---

## 🏗 Install Database Tables

Once database variables are set, run:

```bash
php artisan logbook:install
```

This command creates all required tables.

---

## ⚙️ Configuration

All configuration options live in:

```txt
config/logbook.php
```

Environment variables are used only for sensitive or environment-specific values.

## 🧩 Complete `.env` Reference (all `LOGBOOK_*` vars)

Use this block as a complete starter template:

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

# System log capture
LOGBOOK_SYSTEM_LOGS_ENABLED=true
LOGBOOK_SYSTEM_LOGS_LEVEL=debug
LOGBOOK_SYSTEM_LOGS_BUBBLE=true
LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS=deprecations
LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES=Since symfony/http-foundation,Unable to create configured logger. Using emergency logger.

# Audit behavior
LOGBOOK_AUDIT_EXCLUDE_EVENTS=
LOGBOOK_AUDIT_IGNORE_FIELDS=updated_at,created_at,date,uri,slug
LOGBOOK_AUDIT_MAX_VALUE_LENGTH=2000

# Retention
LOGBOOK_RETENTION_DAYS=365
```

### Variable-by-variable explanation

#### Database (required for addon to work)

- `LOGBOOK_DB_CONNECTION`: DB driver (`mysql`, `pgsql`, ...). Required.
- `LOGBOOK_DB_HOST`: DB host. Required unless using socket-only setup.
- `LOGBOOK_DB_PORT`: DB port. Default `3306`.
- `LOGBOOK_DB_DATABASE`: Database name used by Logbook. Required.
- `LOGBOOK_DB_USERNAME`: DB username. Required.
- `LOGBOOK_DB_PASSWORD`: DB password. Required.
- `LOGBOOK_DB_SOCKET`: Unix socket path (optional). Keep empty for host/port mode.
- `LOGBOOK_DB_CHARSET`: Connection charset. Default `utf8mb4`.
- `LOGBOOK_DB_COLLATION`: Connection collation. Default `utf8mb4_unicode_ci`.

#### System logging

- `LOGBOOK_SYSTEM_LOGS_ENABLED`: Enable/disable automatic Laravel log capture. Default `true`.
- `LOGBOOK_SYSTEM_LOGS_LEVEL`: Minimum captured level (`debug`, `info`, `warning`, `error`, ...). Default `debug`.
- `LOGBOOK_SYSTEM_LOGS_BUBBLE`: Monolog bubble flag for handler interoperability. Default `true`.
- `LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS`: Comma-separated channels to skip. Default includes `deprecations`.
- `LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES`: Comma-separated message fragments to skip (noise guardrails).

#### Audit logging

- `LOGBOOK_AUDIT_EXCLUDE_EVENTS`: Comma-separated full Statamic event class names to exclude from auditing.
- `LOGBOOK_AUDIT_IGNORE_FIELDS`: Comma-separated fields ignored in before/after diffs.
- `LOGBOOK_AUDIT_MAX_VALUE_LENGTH`: Max stored value length before truncation. Default `2000`.

#### Retention

- `LOGBOOK_RETENTION_DAYS`: Days to keep logs before prune. Default `365`.

### Hidden gotchas and important clarifications

- Logbook reads values from `config/logbook.php`; after changing `.env`, run `php artisan config:clear`.
- `LOGBOOK_AUDIT_EXCLUDE_EVENTS` extends built-in exclusions; it does not replace defaults.
- `LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES` splits by comma, so keep each fragment comma-delimited.
- Empty values in comma-based variables are automatically removed by config parsing.
- Privacy masking keys (`password`, `token`, `secret`, ...) are configured in `config/logbook.php` under `privacy.mask_keys` (not via `.env`).

### System Log Capture Controls

```env
LOGBOOK_SYSTEM_LOGS_ENABLED=true
LOGBOOK_SYSTEM_LOGS_LEVEL=debug
LOGBOOK_SYSTEM_LOGS_BUBBLE=true
LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS=deprecations
LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES=Since symfony/http-foundation,Unable to create configured logger. Using emergency logger.
```

- `LOGBOOK_SYSTEM_LOGS_ENABLED`: enable/disable automatic system log capture
- `LOGBOOK_SYSTEM_LOGS_LEVEL`: minimum level captured (`debug`, `info`, `warning`, `error`, ...)
- `LOGBOOK_SYSTEM_LOGS_BUBBLE`: Monolog bubble behavior for handler compatibility
- `LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS`: comma-separated channels to ignore
- `LOGBOOK_SYSTEM_LOGS_IGNORE_MESSAGES`: comma-separated message fragments to ignore

## 🧠 Audit Configuration

### Exclude noisy events (default captures all Statamic events)

```env
LOGBOOK_AUDIT_EXCLUDE_EVENTS="Statamic\\Events\\ResponseCreated,Statamic\\Events\\SearchIndexUpdated"
```

Use `audit_logs.exclude_events` in `config/logbook.php` to block specific event classes while keeping auto discovery enabled.

### Ignore noisy or irrelevant fields

```env
LOGBOOK_AUDIT_IGNORE_FIELDS=updated_at,created_at,slug,uri
```

### Limit stored value size

```env
LOGBOOK_AUDIT_MAX_VALUE_LENGTH=2000
```

Large values are automatically truncated to protect performance and storage.

---

## ✅ Quick Verification

After installation:

1. Run `php artisan logbook:install`
2. Trigger a log, for example in Tinker:
   ```php
   \Log::info('logbook system test', ['source' => 'manual-check']);
   ```
3. Open Logbook in CP and confirm the row appears under **System Logs**
4. Add the Logbook widgets to your dashboard and verify:
   - **Overview** shows 24h system/error/audit totals
   - **Volume by day** renders stacked bars
   - **Live feed** filters correctly using All / Errors / System / Audit

---

## 🗑 Log Retention

Automatically prune old logs after a given number of days:

```env
LOGBOOK_RETENTION_DAYS=365
```

Run manually:

```bash
php artisan logbook:prune
```

You may also schedule this command via cron.

---

## 🔐 Permissions

Logbook registers the following permissions:

- `view logbook` — View system & audit logs
- `export logbook` — Download CSV exports

Assign permissions via the Statamic Control Panel.

---

## ❌ What NOT to Do

- ❌ Do not log secrets, tokens, or passwords
- ❌ Do not modify Logbook database tables manually
- ❌ Do not disable retention without a cleanup strategy
- ❌ Do not treat audit logs as editable content

Logbook is designed to be **read-only from the Control Panel**.

---

## 🧪 Compatibility

| Component | Supported  |
| --------- | ---------- |
| Statamic  | v4, v5, v6 |
| Laravel   | 10, 11     |
| PHP       | 8.1+       |

---

## 📄 License

MIT License
See [`LICENSE`](LICENSE) for details.

---

## 👤 Author

Built and maintained by **Emran Alhaddad**
GitHub: [https://github.com/emran-alhaddad](https://github.com/emran-alhaddad)

---

## 🧾 Changelog

See [`CHANGELOG.md`](CHANGELOG.md) for release history.

## 🚀 Release Notes (latest)

### v1.2.0

- Added three CP dashboard widgets: overview cards, daily trends, and live pulse feed.
- Moved widget styling to native Statamic CP utility/component classes.
- Removed dependency on external frontend asset builds for widget UI.
