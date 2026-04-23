# Changelog

All notable changes to **Statamic Logbook** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

_Nothing queued for the next release yet._

---

## [2.0.1] — 2026-04-23

Hotfix pass after v2.0.0 shipped — addresses the timeline filter pills,
the raw audit event names leaking through the timeline chip, a
float→int PHP 8.3 deprecation emitted by our own overview widget, and
makes the dashboard widgets honour the `view logbook` permission.

### Fixed

* **Timeline severity / type filter pills (`System` / `Audit` /
  `Errors` / `Warnings` / `Info`) didn't filter anything.** The pills
  wrapped hidden `<input type="checkbox">` controls and relied on the
  user clicking a separate **Apply** button after each change, which
  most users (correctly) didn't realise was necessary. They now
  auto-submit the filter form on change (80 ms debounce so rapid
  multi-pill toggles coalesce into a single navigation) and toggle
  the `.lb-pill--active` class immediately for instant visual
  feedback. The checkbox is now visually hidden via `.lb-sr-only`
  instead of `display: none`, keeping it interactive and
  label-clickable across browsers. The form is tagged with
  `data-lb-filter-form`, which also lets the shared empty-param
  stripper keep the resulting URL clean.
* **Timeline audit rows showed raw machine event names
  (`statamic.user.updated`, `statamic.entry.created`, …) instead
  of the humanised labels the Audit Logs page uses.** The timeline
  controller now runs every audit row through
  `AuditActionPresenter::label()` + `variant()` just like
  `audit.blade.php` does, and appends the same
  `changeSummary()` hint to the message line so updates read as
  "Entry saved · title · 'Old' → 'New'". The raw event string
  remains available as the chip's `title` tooltip so operators can
  still grep by machine name (`?action=statamic.user.updated` on
  the Audit page keeps working, unchanged).
* **`Implicit conversion from float … to int loses precision in
  LogbookDashboardData.php` warnings on every dashboard render
  under PHP 8.3+.** `lastErrorAt()` fed the return value of
  `Carbon::diffInMinutes()` (a float in Carbon 3 / Laravel 11+)
  into the `int`-typed `humaniseMinutes()` helper, triggering the
  deprecation — which then got recorded back into the logbook as
  a warning row on every page view. The value is now explicitly
  cast to `int` at the call site.

### Security

* **Dashboard widgets now honour the `view logbook` permission.**
  `LogbookStatsWidget`, `LogbookTrendsWidget`, and
  `LogbookPulseWidget` each declare `canSee()` returning
  `auth()->user()?->can('view logbook')`, but the Statamic widget
  pipeline doesn't invoke `canSee()` consistently across supported
  majors. Each widget's `html()` now short-circuits to a marker
  element (`<div class="lb-widget-gated" hidden>`) when the
  current CP user lacks permission, and the shipped stylesheet
  uses `:has(.lb-widget-gated)` selectors to collapse the
  enclosing dashboard card so no title / surround leaks either.
  Result: non-privileged CP users see no logbook data and no
  evidence the addon is even installed on their dashboard, while
  the utility routes remain gated by `can:view logbook` / `can:export logbook`
  middleware as before.

---

## [2.0.0] — 2026-04-22

Adds first-class Statamic 6 support while keeping Statamic 4 and 5 working
from the same branch. Statamic 3 continues on the dedicated `1.x` LTS branch.

### Added

* **Premium-minimal CP design system.** The addon's Control Panel surface
  has been rebuilt against a tokens-first architecture. Every color,
  spacing step, radius, shadow, font size and motion curve now reads from
  CSS custom properties on `:root`, with dark mode achieved by reassigning
  the same tokens on `.dark`. The visual language shifts to a zinc-neutral
  palette with an indigo/violet accent (`#6366f1` / `#7c3aed`), a 4 px
  spacing baseline, tabular numerics for every count, and a consistent
  11/12/13/14/16/20/24/28 px type scale. Host CP themes continue to work —
  the tokens live on `.lb-page` scope and do not leak into other pages.
* **Overview widget: sparklines + deltas.** The dashboard "Overview"
  widget now renders an inline SVG sparkline for each primary KPI
  (system, errors, audit) using the last 24 hourly buckets, alongside a
  period-over-period delta chip ("↑ +12.4%", "↓ -3%", "— 0%") comparing
  the current 24h window against the prior 24h. A fourth "Error ratio"
  tile replaces the previous three-card layout so the window context is
  visible at a glance.
* **Unified timeline view.** A new third tab on
  `/cp/utilities/logbook/timeline` interleaves system + audit events into
  a single chronological rail, grouped by day with "Today" / "Yesterday"
  labels. Events are filterable by type (system / audit) and severity
  (errors / warnings / info), and each row carries its absolute
  timestamp as a tooltip.
* **Sortable columns.** System and audit tables now accept `sort` and
  `dir` query parameters whitelisted per table (`id`, `created_at`,
  `level`, `channel`, `user_id` for system; `id`, `created_at`, `action`,
  `subject_type`, `user_email` for audit). Column headers render as
  server-driven sort links with correct `aria-sort` state.
* **Density toggle.** A Compact / Cozy / Spacious segmented control in
  the utility toolbar applies a class to every `.lb-table` on the page
  and persists the preference in localStorage under
  `statamic-logbook.density`.
* **Live tail.** System and audit pages gain a pulsing "Live tail"
  toggle that polls a new JSON endpoint (`system.json` / `audit.json`)
  every 5 seconds. When new rows land, the button surfaces a
  "N new · click to refresh" hint; a second click reloads the page with
  the user's filters preserved. Preference persists in localStorage
  under `statamic-logbook.live-tail`.
* **Saved filter presets.** A "Presets ▾" menu on system + audit pages
  snapshots the current query string under a user-chosen name. Presets
  are stored per-page in localStorage under
  `statamic-logbook.presets.<scope>` and can be applied or deleted from
  the menu.
* **Row-as-JSON export.** Every system / audit row gains a "JSON" action
  that opens the existing modal viewer pre-populated with the full
  record as pretty-printed JSON, making copy-to-clipboard a two-click
  operation.
* **Keyboard shortcuts.** `/` focuses the page's primary search input;
  `g s` navigates to system logs; `g a` navigates to audit logs.
  Shortcuts are suppressed while typing in form fields.
* **Relative-time tooltips.** All tabular timestamps now render as a
  humanised relative time (e.g. "2 minutes ago") with the absolute UTC
  timestamp exposed via the `title` attribute.
* **Empty states.** Every widget + utility-page surface now renders a
  consistent `.lb-empty` illustration + hint when there is nothing to
  show, instead of collapsing to "—".
* **`LogbookDashboardData` extensions.** `summary()` now also returns
  `systemSpark24h` / `errorSpark24h` / `auditSpark24h` (24 hourly
  integers each), the corresponding `*Prev` counts for the prior 24h
  window, and `systemDelta` / `errorDelta` / `auditDelta` as
  `{ value, pct, direction }` tuples. The hourly bucket query is
  driver-aware (SQLite `strftime`, Postgres `to_char`, MySQL / MariaDB
  `DATE_FORMAT`). A new `delta()` helper is exposed for reuse.
* **`LogbookUtilityController` endpoints.** `timeline()`, `systemJson()`,
  `auditJson()` back the three new features above, with
  `can:view logbook` middleware.
* **Error signature panel + last-error-ago chip.** The dashboard overview
  cards now surface a small "Last error 3h ago" / "No errors in 72h"
  chip under the errors card, and the widget gains a new
  "Top error signatures · 24h" section that groups recent errors by a
  normalised fingerprint (UUIDs, hex blobs, IPs, emails, quoted strings
  and bare numbers collapse to placeholders) so "User 42 not found" and
  "User 99 not found" share a single row with a count + last-seen
  timestamp. Each signature deep-links to the system log pre-filtered
  by level and a truncated example query.
* **Busiest-hour card.** The four-up overview replaces the "Error
  ratio" tile with a "Busiest hour · 24h" card showing the peak count
  and the local `HH:00` slot it landed in, computed from the existing
  `systemSpark24h` series so there's no extra DB hit.
* **7×24 channel heatmap.** `LogbookTrendsWidget` now renders a
  day-by-day × hour-of-day grid below the existing daily bars, coloured
  by volume via `color-mix(in srgb, var(--lb-accent) calc(var(--lb-intensity) * 100%), var(--lb-bg-subtle))`
  with a light/dark-aware base. Cells carry a native tooltip with the
  exact day, hour and line count.
* **Meaningful density toggle.** Compact / Cozy / Spacious is no longer
  a font-size switch — it swaps `.lb-density--{mode}` on the `.lb-page`
  root and drives padding, typography, clamp behaviour, filter and
  stat sizing, and secondary-meta visibility at once. Compact hides
  row meta, forces single-line truncation and shrinks chips + the
  filter/stat grid; Spacious releases the cell clamp, lets messages
  wrap, and enlarges the toolbar. Legacy `comfortable` values in
  localStorage are silently migrated to `cozy`.
* **Chip-style paginator + per-page selector.** System and audit
  pages share a new `_pagination.blade.php` partial rendering a
  result-range readout ("Showing 51 – 100 of 2,431"), a `[25|50|100|200]`
  per-page dropdown that submits on change (preserving every other
  query param), and a custom Laravel paginator view (`_paginator.blade.php`)
  that uses `.lb-pager` chip buttons with Prev/1/2/…/Next and SVG
  chevrons instead of the default bootstrap-4 markup. `paginate()`
  now honours the selected per-page value via a new
  `resolvePerPage(Request)` helper.
* **`logbook_user_prefs` table + `UserPrefsRepository`.** A new
  `logbook_user_prefs` table ships with `logbook:install`, stored in
  the logbook DB (not the project DB) so the addon stays
  self-contained and a full uninstall is still "drop the logbook
  database". One row per user, a single JSON `prefs` blob, scalar
  `key → value` API via `UserPrefsRepository::get()` / `set()` / `all()`
  / `forget()`. All methods fail soft if the table is missing or the
  connection is down — the UI continues to use localStorage as a
  zero-config fallback. Four CP endpoints (`GET /prefs`,
  `GET /prefs/{key}`, `PUT /prefs/{key}`, `DELETE /prefs/{key}`) are
  registered under `can:view logbook` for cross-device sync of
  density, saved presets and per-page defaults.

* **Statamic 6 support.** The addon now boots cleanly on Statamic 6 without
  clobbering the core `statamic.widgets` extension binding.
* **Self-contained CP stylesheet.** `resources/dist/statamic-logbook.css`
  is now registered via `$stylesheets` on `LogbookServiceProvider` and
  auto-injected into the CP `<head>` by Statamic. All widget and utility
  view styles (cards, feed rows, pill filters, stacked bar chart, level/
  action badges, buttons, cards, inputs, tabs, tables, modal) are now
  addon-owned under the `lb-*` namespace and no longer depend on Tailwind
  utilities that the host CP's JIT build may have purged, nor on CP
  component classes (`.btn`, `.btn-primary`, `.card`, `.input-text`,
  `.data-table`) that Statamic 6 stripped of their visual surface. Run
  `php artisan vendor:publish --tag=logbook` after install (Statamic
  auto-runs this on `statamic:install`).
* **Self-contained CP script.** `resources/dist/statamic-logbook.js` is
  registered via `$scripts` on `LogbookServiceProvider` and auto-injected
  into the CP `<head>`. It installs document-level delegated listeners
  for the pulse widget's filter pills, the utility page's Prune / Flush
  Spool CTAs and the context / changes / request modal viewer. This is
  required because Statamic 6's `DynamicHtmlRenderer` compiles widget
  HTML through `defineComponent({ template: widget.html })`, and Vue's
  template compiler strips `<script>` tags (even under `v-pre`), so
  scripts embedded in widget Blade never execute.
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
* **Pre-minified CP bundles + build harness.** The addon now ships
  `resources/dist/statamic-logbook.min.css` (~48 KB, down from
  ~67 KB) and `resources/dist/statamic-logbook.min.js` (~14 KB,
  down from ~38 KB) alongside the annotated source files, and
  registers the minified variants via `$stylesheets` / `$scripts`.
  A lightweight `package.json` with `npm run build` (clean-css-cli
  + terser) is included so contributors can regenerate the bundles
  after changing the source files.

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
* **Human-readable audit actions + inline change hints.** Raw event
  strings like `statamic.user.saved` / `statamic.entry.saved` /
  `statamic.user.login` are now presented as "User updated" /
  "Entry saved" / "User logged in" via a view-layer `AuditActionPresenter`
  that inspects the action verb + the `changes` JSON to distinguish
  create / update / delete / login / logout. For `update` events, the
  audit table renders a truncated "from → to" ribbon below the row
  summarising the first 1–2 changed fields (e.g. `title: "Old" → "New"`)
  using the existing `changes` column. Zero schema changes — all
  derivation is server-side view prep against data already stored.
* **Live tail is now self-scheduling + adaptive.** Replaced the
  fixed-interval `setInterval` poll with a `setTimeout` self-chain
  driven by an `AbortController`. The tick pauses on
  `visibilitychange` hidden, pauses on `offline`, resumes on `online` /
  visible, aborts the in-flight request on toggle-off, and cleans up
  on `pagehide` / `beforeunload`. Backoff is exponential with jitter
  (5s → 10s → 20s → 40s, capped at 60s) on consecutive errors, and
  relaxes toward the upper bound when the server returns no new rows
  for several ticks. Result: four independent mechanisms now reduce
  server load (visibility, network state, idle relaxation, error
  backoff) where previously a stalled tab would hammer the endpoint
  every 5 seconds indefinitely.
* **Cell truncation on system + audit tables.** Long messages, user
  ids / emails, action strings and subject titles are now clamped to
  a single line with a `.lb-cell-clamp` treatment; the full value
  remains available via the row's JSON modal. Compact density forces
  the clamp globally; Spacious density releases it so long messages
  wrap.
* Minimum `statamic/cms` raised to `^4.0|^5.0|^6.0`. Statamic 3 users must
  use the `1.x` LTS branch.
* Minimum `illuminate/support` and `illuminate/database` raised to
  `^9.0|^10.0|^11.0|^12.0`.
* PHP constraint widened to `^8.1|^8.2|^8.3|^8.4`.
* PHPUnit constraint widened to `^10.0|^11.0` to match the testbench matrix.

### Fixed

* **Addon is now discoverable by Statamic.** Added the missing
  `extra.statamic` block to `composer.json`. Statamic's
  `\Statamic\Addons\Manifest::build()` filters `vendor/composer/installed.json`
  to packages that declare `extra.statamic`; without it, the addon was
  silently absent from `Addon::all()`, `AddonServiceProvider::getAddon()`
  returned `null`, and the entire Statamic boot chain (including
  `bootWidgets`, `bootCommands`, and `bootAddon`) was skipped. The legacy
  `statamic.widgets` rebind was a workaround hiding this misconfiguration
  on v5. On Statamic 6 it broke core widget registration while still not
  actually running the boot chain, producing `WidgetNotFoundException` on
  the dashboard.
* **Statamic 6 boot regression.** Removed the eager
  `$this->app->bind('statamic.widgets', …)` rebind in
  `LogbookServiceProvider::register()` that clobbered
  `Statamic\Providers\ExtensionServiceProvider::registerBindingAlias()` and
  broke widget registration on Statamic 6.
* **Widget registry shim firing order.** The shim no longer wraps its own
  call in a nested `Statamic::booted(...)`. `bootAddon()` runs inside the
  booted callback already, and `Statamic::runBootedCallbacks()` iterates
  a snapshot and empties the queue, so any callback queued from within
  would never fire. The shim now runs synchronously at the tail of
  `bootAddon()`, after the parent chain's `bootWidgets()` has already
  populated the core binding.
* **Cross-major class-not-found fatals.** All Statamic event references are
  now string FQCNs filtered through `class_exists()` before `Event::listen()`
  so majors that have removed or renamed an event class never produce a
  fatal at addon boot.
* **`StatamicAuditSubscriber::isEntryEvent()`**: no longer hard-imports
  `\Statamic\Entries\Entry` at the top of the file, which previously forced
  autoload of a class that may not exist in every supported major. The
  check is now `class_exists()`-gated with a duck-typed fallback.
* **Unstyled CP widgets on Statamic 6.** The dashboard widgets and utility
  pages previously rendered as unstyled gray boxes on Statamic 6 because
  the CP Tailwind JIT build scans only CP source paths for utility class
  usage and purged everything our addon views referenced from that
  compilation — including `text-4xs`, `text-3xl`, `tracking-widest`,
  `bg-dark-*`, every `dark:*` variant, `bg-orange`, `text-blue`, the
  `subhead` / `icon-header-avatar` / `pill-tab` CP components, arbitrary
  widths like `w-40` / `max-w-[720px]`, and `shadow-2xl` / `backdrop-blur`.
  All widget and utility templates have been rewritten against an
  addon-owned `lb-*` stylesheet shipped via `$stylesheets` so rendering is
  now independent of the host CP's compiled Tailwind surface. This fixes
  rendering on Statamic 6 and hardens us against future CP Tailwind
  config changes on 4 / 5.
* **Pulse widget filter pills ignoring clicks on Statamic 6.** The pills
  wired themselves up via a `<script>` tag inside the widget Blade. On
  Statamic 6 that script never executed because the CP renders widget
  HTML through `DynamicHtmlRenderer` (`defineComponent({ template })`)
  and Vue's template compiler strips every `<script>` tag. The filter
  logic (plus the command / modal handlers on the utility page) now lives
  in `resources/dist/statamic-logbook.js`, registered via `$scripts` on
  `LogbookServiceProvider` and loaded into the CP `<head>` outside the
  Vue-compiled widget subtree.
* **Error-card badge overlap.** The "Attention" / "OK" pill in the errors
  card no longer collides with the numeric value — both states now share
  the same absolutely positioned top-right anchor so the card height is
  stable between states.
* **Utility page chrome (buttons, card box, inputs, tabs, table).** On
  Statamic 6 the legacy CP component classes `.btn`, `.btn-primary`,
  `.card`, `.input-text`, `.data-table` lost most of their visual
  surface (e.g. `.btn` became a `margin-right: 15px` utility). The
  utility page now uses `.lb-btn`, `.lb-btn--primary`, `.lb-box`,
  `.lb-input`, `.lb-tabs`, `.lb-table`, `.lb-stat`, `.lb-modal__*`
  component classes from our shipped stylesheet, with explicit
  light/dark variants throughout.
* **`Undefined property: Illuminate\View\Factory::$yieldContent` on
  utility page render.** The previous `_layout.blade.php` embedded an
  inline `<style>` block whose comment read "Scoped via the @yield
  content wrapper". Blade's statement compiler accepts `@yield` with
  an *optional* expression group — the regex matches `@yield` even
  without parens — and `compileYield(null)` emits the raw property
  reference `$__env->yieldContent` (no `echo`, no parens) into the
  compiled template. At render time PHP then fails on an undefined
  property access. The inline `<style>` has been dropped; the
  container width override it contained has moved into the shipped
  stylesheet (`.content .container:has(.lb-page)` and
  `#main .page-wrapper:has(.lb-page)`) scoped to logbook pages so the
  override can't bleed into unrelated CP pages.

* **Top spacing on the utility panel.** The `.lb-page` root now carries
  an explicit top padding step so the CP header no longer butts against
  the title row on Statamic 6 where the host shell margin collapsed.
* **Row JSON modal flush against the CP top nav.** `#logbook-modal`
  used `padding: var(--lb-s-10) var(--lb-s-4)` (40 px top) which on
  Statamic 6 — where the host shell exposes a two-row breadcrumb +
  locale bar above the CP content — left the modal header touching
  the top chrome. The top padding now uses
  `clamp(5rem, 10vh, 7rem)` so the dialog always clears the host
  nav regardless of which shell variant is rendered.
* **"Presets ▾" dropdown not opening.** The dropdown lived inside
  `<form class="lb-filter--sticky">`, which uses
  `backdrop-filter: blur(8px)`. Per the CSS spec, `backdrop-filter`
  creates a containing block for `position: fixed` descendants, so
  the menu's "fixed" coordinates were being interpreted relative to
  the toolbar instead of the viewport — landing it off-screen on
  many layouts. The open/close flow now portals the menu element
  into `<body>` while it's open (and returns it to its original
  parent on close), escaping the containing-block trap. Click and
  keyboard delegation was updated to resolve the preset root via a
  `data-lb-preset-menu-id` handle rather than DOM ancestry so every
  Apply / Delete / Save action keeps working across the portal.
* **Filter dropdowns beside the date pickers not applying.** The
  level / channel / action / subject `<select>` controls submitted
  empty `""` values alongside the real filters, which the controller
  happily round-tripped into the query string. The filter form now
  carries hidden `sort` / `dir` / `per_page` inputs so those aren't
  nuked on submit, and a pre-submit handler disables every empty
  `<select>` / `<input>` so they're stripped from the resulting URL
  by the browser. Result: filters actually narrow results, the URL
  stays readable, and sort/per-page state is preserved across filter
  changes.

### Removed

* Removed the eager `statamic.widgets` container binding override. The
  widget registry is now left to core on Statamic 6 and shimmed only when a
  handle is missing.

### Security

* Continued strict filtering of event class names before `Event::listen()`
  to avoid untrusted class-loading from the config file.
* Spool ingestion continues to use `LOCK_EX` on write paths (unchanged).
* **Server path scrubbing on CP command output.** The Prune / Flush
  Spool CP actions pipe `Artisan::output()` back to the browser as
  JSON. Absolute filesystem paths that might appear in that stream —
  either from our own "failed spool file" message, or from a Laravel
  stack-trace-like `at /var/www/app/vendor/...` line in an
  exception — are now stripped to their basename by a new
  `scrubPaths()` helper in `LogbookUtilityController`, and the
  `FlushSpoolCommand` itself now reports only `failed_name`
  (basename) instead of `failed_path` (absolute) in its result
  array. Result: CP users never see server filesystem layout or
  storage roots, even on command failures. Operators with shell
  access still see the full path locally via the normal Artisan
  invocation — scrubbing applies only at the CP boundary.

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

