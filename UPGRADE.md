# Upgrading Statamic Logbook

## 2.0.x → 2.1.0

**Your data is safe.** Neither log table changed shape in 2.1.0, so every row
you already have stays exactly where it is and remains queryable, filterable
and exportable. The only schema change is one new table, `logbook_settings`.

What *does* need attention is your **configuration**. Before 2.1.0 everything
was configured through `.env`; from 2.1.0 the Control Panel owns it. Logbook
imports your existing configuration for you — this guide explains what happens
and how to verify it.

---

### Upgrade

```bash
composer update emran-alhaddad/statamic-logbook
php artisan logbook:upgrade
```

`logbook:upgrade` is idempotent — safe to run more than once, and safe to run
during a deploy. It does two things:

1. Creates tables added since your installed version (existing tables are left
   untouched).
2. Imports your `.env` / `config/logbook.php` values into the CP settings row
   and marks the install configured, so you are not sent through first-run
   database setup.

It prints exactly what it imported:

```
Imported your existing configuration into CP settings:
  • System logs:      on
  • Captured levels:  emergency, alert, critical, error, warning
  • Audit logs:       on
  • Disabled events:  2
  • Retention:        90 days
  • Page views:       off (new feature — enable it in Settings if you want it)
```

#### If you forget to run it

Nothing breaks. The first time someone opens *Utilities → Logbook → Settings*,
Logbook detects the existing install and performs the same import
automatically. Running the command is simply the explicit, visible version —
useful in a deploy script where you want the summary in your logs.

---

### What gets imported

| Your old setting | Becomes |
| --- | --- |
| `LOGBOOK_SYSTEM_LOGS_ENABLED` | System Logs master switch |
| `LOGBOOK_SYSTEM_LOGS_LEVEL` | Per-level capture switches (see below) |
| `LOGBOOK_AUDIT_LOGS_ENABLED` | Audit Logs master switch |
| `LOGBOOK_AUDIT_EXCLUDE_EVENTS` | The matching events shown as switched off |
| `LOGBOOK_RETENTION_DAYS` | Retention (days) |

**Log levels deserve a note.** Before 2.1.0 you set a single minimum severity
(`LOGBOOK_SYSTEM_LOGS_LEVEL`); now each level has its own switch. The import
enables every level *at or above* your old threshold and leaves the rest off.
So `LOGBOOK_SYSTEM_LOGS_LEVEL=warning` becomes emergency, alert, critical,
error and warning — **not** all eight. This is deliberate: switching every
level on would multiply your log volume overnight.

**Page-view tracking stays off.** It is new, and it is higher volume than
change auditing, so an upgrade never enables it for you. Turn it on under
*Settings → Page Views* when you want it.

**Your `.env` still works.** `LOGBOOK_*` values continue to act as defaults;
the CP settings simply take precedence. Nothing is written to or removed from
your `.env` by the upgrade. The ignore lists
(`LOGBOOK_SYSTEM_LOGS_IGNORE_CHANNELS`, `LOGBOOK_AUDIT_IGNORE_FIELDS`) are
merged *on top of* whatever you add in the CP, so they keep applying and the
CP's "extra" boxes stay empty rather than duplicating them.

---

### Verify the upgrade

```bash
# Tables — logbook_settings should now exist alongside your log tables
php artisan logbook:upgrade          # re-run: reports "settings already exist"

# Your rows are still there
php artisan tinker
>>> DB::connection(config('logbook.db.connection') ? 'logbook' : null)
...     ->table('logbook_audit_logs')->count();
```

Then open *Utilities → Logbook → Settings* and confirm the switches match what
your `.env` used to say. If anything looks wrong, re-import with:

```bash
php artisan logbook:upgrade --force
```

`--force` overwrites the CP settings from `.env` / `config/logbook.php`. It
never touches log rows.

---

### Rolling back

2.1.0 adds a table and changes no existing columns, so downgrading to 2.0.x is
safe — the older code simply ignores `logbook_settings` and reads `.env` again.
Your `.env` was never modified, so the old configuration is still there.

If you want to discard the CP settings and go back to env-only configuration
while staying on 2.1.0:

```sql
DELETE FROM logbook_settings WHERE `key` = 'settings';
```

With no settings row, `.env` becomes authoritative again. (Opening the settings
screen will re-import from `.env`, which produces the same result.)

---

### Republish the CP assets

2.1.0 ships a new stylesheet and script bundle:

```bash
php artisan vendor:publish --tag=logbook --force
php artisan cache:clear
```

The `cache:clear` matters: Statamic caches the addon's asset URL (including its
cache-busting version) indefinitely, so without it browsers keep serving the
old bundle and the new screens look unstyled.

---

## 1.x → 2.x

Statamic 3 sites stay on the [`1.x` LTS branch](https://github.com/emran-alhaddad/statamic-logbook/tree/1.x).
Moving a Statamic 3 site to 2.x means upgrading Statamic itself first; the
Logbook tables are compatible, so your history survives the move.
