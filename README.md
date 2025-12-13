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
- No frontend frameworks or dependencies

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

---

## 🧠 Audit Configuration

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
