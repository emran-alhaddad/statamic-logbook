# Statamic Logbook

## What it is

A Statamic CMS addon that records system logs and user audit trails into a
database and surfaces them inside the Control Panel: filterable log tables,
a merged timeline, dashboard widgets, CSV export, and a CP-managed settings
page with first-run database setup.

## Who uses it

Site operators and agency developers administering Statamic sites — usually
in the CP for minutes at a time, mid-task (reviewing who changed what,
chasing an error). Desktop, office lighting, both light and dark CP themes.

## Register

product — admin/dashboard UI. Design serves the data; the addon must feel
native to the Statamic Control Panel, not like a foreign marketing page.

## Design system

Hand-authored stylesheet shipped with the addon (`resources/dist/
statamic-logbook.css`, minified twin auto-published to the CP):

- Prefix `lb-`, BEM-ish (`lb-btn`, `lb-btn--primary`, `lb-chip--delete`).
- Tokens: zinc neutral ramp (`--lb-zinc-*`), indigo accent
  (`--lb-accent-*`), semantic status (`--lb-ok/warn/danger/info-*`),
  spacing `--lb-s-1…16` (4px base), radii `--lb-radius-*`, shadows
  `--lb-shadow-xs…xl`, system font stacks.
- Dark theme via `.dark` overrides on the same custom properties — every
  component must read correctly in both themes.
- Existing vocabulary to reuse: `lb-box`, `lb-panel`, `lb-card`,
  `lb-stat`, `lb-tabs/lb-tab`, `lb-btn` (+ `--primary/--ghost/--sm`),
  `lb-input`, `lb-chip`/`lb-badge` status variants, `lb-empty`,
  `lb-toolbar`, `lb-table`.
- New components join the stylesheet source and are rebuilt into the
  `.min.css` via `npm run build:css` (clean-css). No inline styles in
  blades; no CP Tailwind utilities (they get purged by the host build).

## Constraints

- Blades render inside the Statamic CP layout; widget HTML additionally
  passes through Vue's template compiler (no inline `<script>` in widgets;
  utility pages may use them).
- Accessibility: keyboard reachable, focus-visible rings, WCAG AA contrast
  in both themes.
