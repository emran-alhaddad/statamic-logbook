/*!
 * Statamic Logbook — Control Panel runtime
 * ----------------------------------------------------------
 * Self-contained JS registered via $scripts on
 * LogbookServiceProvider and auto-injected by Statamic into
 * the CP <head>.
 *
 * Why this file exists
 * --------------------
 * Statamic 6's dashboard renders addon widget HTML through a
 * DynamicHtmlRenderer that does:
 *
 *     defineComponent({ template: widget.html })
 *
 * i.e. each widget's raw HTML is handed to the Vue template
 * compiler at runtime. Vue's compiler strips any <script>
 * tags from templates before compilation, regardless of
 * `v-pre`. That means filter/command JS embedded in Blade
 * widget views simply never executes on v6. Shipping this
 * file via $scripts puts the handlers in the page once, at
 * CP boot, where they can listen for events bubbling up from
 * the Vue-mounted widget DOM via document-level delegation.
 *
 * All handlers here are idempotent (guarded by `window.__logbook*`
 * flags) so we survive HMR reloads and repeated injections.
 *
 * No framework dependencies — this is vanilla browser JS.
 */
(function () {
    'use strict';

    if (window.__logbookLoaded) return;
    window.__logbookLoaded = true;

    // ------------------------------------------------------
    // 1. Pulse widget filter pills (dashboard)
    // ------------------------------------------------------
    // The pulse widget renders its rows with data-lb-type and
    // data-lb-sev attributes, and its filter buttons with a
    // data-lb-filter attribute. We listen at document level,
    // so the listener is immune to Vue re-mounting the widget
    // subtree.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-lb-filter]');
        if (!btn) return;

        var root = btn.closest('.logbook-pulse-root');
        if (!root) return;

        var mode = btn.getAttribute('data-lb-filter') || 'all';
        var rows = root.querySelectorAll('.logbook-pulse-row');
        var btns = root.querySelectorAll('[data-lb-filter]');

        rows.forEach(function (el) {
            var t = el.getAttribute('data-lb-type');
            var s = el.getAttribute('data-lb-sev');
            var show = false;
            if (mode === 'all') show = true;
            else if (mode === 'errors') show = (s === 'error');
            else if (mode === 'audit') show = (t === 'audit');
            else if (mode === 'info') show = (t === 'system' && s === 'info');
            el.classList.toggle('lb-hidden', !show);
        });

        btns.forEach(function (b) {
            b.classList.toggle('lb-pill--active', b === btn);
        });
    });

    // ------------------------------------------------------
    // 2. Utility page: Prune / Flush Spool command CTAs
    // ------------------------------------------------------
    // Buttons on the utility page carry:
    //   data-lb-command="prune" | "flush-spool"
    //   data-lb-command-url="<cp_route url>"
    //   data-lb-command-label="Prune" | "Flush Spool"
    //   data-lb-csrf="<csrf token>"
    //
    // Posts a URL-encoded form so Laravel's VerifyCsrfToken
    // middleware is happy, surfaces toasts via the Statamic
    // global when available and falls back to `alert()`.
    var commandState = Object.create(null);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-lb-command]');
        if (!btn) return;
        e.preventDefault();
        runCommand(btn);
    });

    function runCommand(button) {
        var url = button.getAttribute('data-lb-command-url');
        var key = button.getAttribute('data-lb-command') || '';
        var label = button.getAttribute('data-lb-command-label') || 'Command';
        var token = button.getAttribute('data-lb-csrf') || getCsrfToken();
        if (!url || commandState[key]) return;

        var originalText = button.textContent;
        commandState[key] = true;
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
        button.textContent = label + '…';
        toast('info', label + ': in-progress');

        var body = new URLSearchParams();
        body.append('_token', token || '');

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (response.ok && data && data.ok) {
                    toast('success', label + ': done');
                } else {
                    var message = (data && data.message) ? data.message : ('HTTP ' + response.status);
                    toast('error', label + ': failed (' + message + ')');
                }
            });
        }).catch(function (err) {
            toast('error', label + ': failed (' + ((err && err.message) || 'request error') + ')');
        }).finally(function () {
            commandState[key] = false;
            button.disabled = false;
            button.removeAttribute('aria-disabled');
            button.textContent = originalText;
        });
    }

    // ------------------------------------------------------
    // 3. Utility page: modal viewer for context / changes /
    //    request details
    // ------------------------------------------------------
    // Triggers: elements with `data-lb-modal-title`,
    //           `data-lb-modal-payload` (base64-utf8),
    //           `data-lb-modal-subtitle`.
    document.addEventListener('click', function (e) {
        var opener = e.target.closest && e.target.closest('[data-lb-modal-payload]');
        if (!opener) return;
        e.preventDefault();
        openModal(
            opener.getAttribute('data-lb-modal-title') || 'Details',
            opener.getAttribute('data-lb-modal-payload') || '',
            opener.getAttribute('data-lb-modal-subtitle') || ''
        );
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest) return;
        if (e.target.closest('[data-lb-modal-close]')) {
            e.preventDefault();
            closeModal();
        } else if (e.target.closest('[data-lb-modal-copy]')) {
            e.preventDefault();
            copyModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // ------------------------------------------------------
    // 4. Utility page: density toggle (Compact / Cozy / Spacious)
    // ------------------------------------------------------
    // Applies `.lb-density--{mode}` to every `.lb-page` on screen. CSS
    // rules hang off that root class, so a single class swap retunes:
    //   - table row padding + font size
    //   - whether secondary meta lines show (compact hides them)
    //   - whether cells are forced to truncate (compact) or wrap (spacious)
    //   - filter control / stat-grid sizing
    //
    // Persists the user's choice in localStorage. Buttons carry
    // `data-lb-density="<mode>"` and render an aria-pressed state.
    var DENSITY_KEY = 'statamic-logbook.density';
    var DENSITY_MODES = ['compact', 'cozy', 'spacious'];
    // Legacy class names left in place for any callers in older views.
    var LEGACY_TABLE_CLASSES = ['lb-table--compact', 'lb-table--spacious'];
    var ROOT_DENSITY_CLASSES = DENSITY_MODES.map(function (m) { return 'lb-density--' + m; });

    function applyDensity(mode) {
        if (DENSITY_MODES.indexOf(mode) === -1) mode = 'cozy';

        // Root-level density class drives the bulk of CSS rules.
        var pages = document.querySelectorAll('.lb-page');
        pages.forEach(function (page) {
            ROOT_DENSITY_CLASSES.forEach(function (c) { page.classList.remove(c); });
            page.classList.add('lb-density--' + mode);
        });

        // Legacy table class swap kept for back-compat.
        var tables = document.querySelectorAll('.lb-page .lb-table');
        tables.forEach(function (t) {
            LEGACY_TABLE_CLASSES.forEach(function (c) { t.classList.remove(c); });
            if (mode === 'compact')  t.classList.add('lb-table--compact');
            if (mode === 'spacious') t.classList.add('lb-table--spacious');
        });

        var btns = document.querySelectorAll('[data-lb-density]');
        btns.forEach(function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-lb-density') === mode ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-lb-density]');
        if (!btn) return;
        var mode = btn.getAttribute('data-lb-density') || 'cozy';
        try { localStorage.setItem(DENSITY_KEY, mode); } catch (_) { /* ignore quota / private mode */ }
        applyDensity(mode);
    });

    // Re-apply stored preference on DOMContentLoaded
    function initDensity() {
        var mode = 'cozy';
        try {
            var stored = localStorage.getItem(DENSITY_KEY);
            // Migrate historical 'comfortable' label → 'cozy'.
            if (stored === 'comfortable') stored = 'cozy';
            if (stored && DENSITY_MODES.indexOf(stored) !== -1) mode = stored;
        } catch (_) {}
        applyDensity(mode);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDensity);
    } else {
        initDensity();
    }

    // ------------------------------------------------------
    // 5. Utility page: live tail toggle
    // ------------------------------------------------------
    // Polls a JSON endpoint for new rows and shows an "N new — click
    // to refresh" nudge when the server has fresher data than the
    // currently-rendered table.
    //
    // This version is optimised for resource-friendliness:
    //
    //   * Uses a `setTimeout` self-chain instead of `setInterval` so
    //     it never queues up polls behind a stalled request.
    //   * Pauses when the tab is hidden (visibilitychange) — browsers
    //     already throttle background timers, but skipping the fetch
    //     outright is cheaper and avoids a backlog of stale requests
    //     piling up the moment the tab refocuses.
    //   * Pauses when the browser reports offline.
    //   * Aborts the in-flight request on toggle-off, navigation,
    //     or visibility change.
    //   * Exponential backoff on consecutive errors (5 → 10 → 20 →
    //     max 60 seconds), with jitter to avoid thundering-herd on
    //     shared backends. Backoff resets after a successful poll.
    //   * Idle relaxation: after N consecutive empty responses the
    //     interval stretches (1× → 2× → 4×, capped) so an idle
    //     site quietly drops to ~20s polling instead of hammering
    //     the server every 5s. First non-empty response snaps it
    //     back.
    //   * Skips a poll if the previous request hasn't returned yet.
    //
    // Preference persists under localStorage key
    //   statamic-logbook.live-tail
    // so the tail resumes after a manual refresh.
    var LIVE_TAIL_KEY  = 'statamic-logbook.live-tail';
    var LIVE_TAIL_BASE = 5000;   // base poll period (ms)
    var LIVE_TAIL_MAX  = 60000;  // upper bound for backoff/idle
    var liveTailTimer    = null;
    var liveTailAbort    = null;
    var liveTailInFlight = false;
    var liveTailLastCount = 0;
    var liveTailErrors    = 0;
    var liveTailIdleHits  = 0; // consecutive empty polls

    function currentLiveTailButton() {
        return document.querySelector('[data-lb-live-tail][aria-pressed="true"]');
    }

    function nextLiveTailDelay() {
        // Backoff after errors beats idle relaxation — errors can mean
        // the server is sick and we want to give it air.
        if (liveTailErrors > 0) {
            var backed = LIVE_TAIL_BASE * Math.pow(2, Math.min(liveTailErrors, 4));
            var jitter = Math.random() * 500;
            return Math.min(LIVE_TAIL_MAX, backed + jitter);
        }
        if (liveTailIdleHits > 0) {
            // 5s → 10s → 20s → 40s, capped at LIVE_TAIL_MAX
            var stretch = LIVE_TAIL_BASE * Math.pow(2, Math.min(liveTailIdleHits, 3));
            return Math.min(LIVE_TAIL_MAX, stretch);
        }
        return LIVE_TAIL_BASE;
    }

    function liveTailTick(button, url) {
        // Invariant: only fires while the button is still armed AND
        // the tab is visible AND we're online. Any of those flipping
        // cancels this cycle.
        if (!button || button.getAttribute('aria-pressed') !== 'true') return;
        if (document.hidden) { scheduleLiveTailPoll(button, url); return; }
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            scheduleLiveTailPoll(button, url);
            return;
        }
        if (liveTailInFlight) { scheduleLiveTailPoll(button, url); return; }

        var afterId = Number(button.getAttribute('data-lb-live-tail-after') || 0);

        // AbortController lets us bin the request if the user toggles
        // off mid-flight — saves the server a wasted round-trip and
        // prevents a late response from mutating state after the
        // user thought they'd stopped.
        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        liveTailAbort = controller;
        liveTailInFlight = true;

        fetch(url + '?after_id=' + encodeURIComponent(afterId) + '&limit=1', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: controller ? controller.signal : undefined
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                liveTailInFlight = false;
                if (controller !== liveTailAbort) return; // superseded
                if (!j) { liveTailErrors++; return; }

                liveTailErrors = 0;
                var count = Number(j.count || 0);
                if (count > 0) {
                    liveTailIdleHits = 0;
                    liveTailLastCount += count;
                    var nextId = Number(j.latest_id || afterId);
                    button.setAttribute('data-lb-live-tail-after', String(nextId));
                    var label = button.querySelector('[data-lb-live-tail-label]');
                    if (label) label.textContent = liveTailLastCount + ' new · click to refresh';
                } else {
                    liveTailIdleHits++;
                }
            })
            .catch(function (err) {
                liveTailInFlight = false;
                if (err && err.name === 'AbortError') return; // user-initiated
                liveTailErrors++;
            })
            .finally(function () {
                scheduleLiveTailPoll(button, url);
            });
    }

    function scheduleLiveTailPoll(button, url) {
        if (liveTailTimer) { clearTimeout(liveTailTimer); liveTailTimer = null; }
        if (!button || button.getAttribute('aria-pressed') !== 'true') return;
        liveTailTimer = setTimeout(function () { liveTailTick(button, url); }, nextLiveTailDelay());
    }

    function startLiveTail(button) {
        stopLiveTail(); // single-flight
        var url = button.getAttribute('data-lb-live-tail-json');
        if (!url) return;

        liveTailErrors = 0;
        liveTailIdleHits = 0;

        // Seed from an immediate probe so the first poll doesn't
        // return the currently-visible head row as "new".
        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        liveTailAbort = controller;

        fetch(url + '?limit=1', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: controller ? controller.signal : undefined
        })
            .then(function (r) { return r.ok ? r.json() : { latest_id: 0 }; })
            .then(function (j) {
                if (controller !== liveTailAbort) return;
                var baseId = Number(j.latest_id || 0);
                button.setAttribute('data-lb-live-tail-after', String(baseId));
                scheduleLiveTailPoll(button, url);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                liveTailErrors++;
                scheduleLiveTailPoll(button, url);
            });

        button.setAttribute('aria-pressed', 'true');
        try { localStorage.setItem(LIVE_TAIL_KEY, '1'); } catch (_) {}
    }

    function stopLiveTail() {
        if (liveTailTimer) { clearTimeout(liveTailTimer); liveTailTimer = null; }
        if (liveTailAbort && typeof liveTailAbort.abort === 'function') {
            try { liveTailAbort.abort(); } catch (_) {}
        }
        liveTailAbort = null;
        liveTailInFlight = false;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-lb-live-tail]');
        if (!btn) return;

        if (liveTailLastCount > 0 && btn.getAttribute('aria-pressed') === 'true') {
            // Click-through: reload with filters preserved.
            window.location.reload();
            return;
        }

        if (btn.getAttribute('aria-pressed') === 'true') {
            stopLiveTail();
            btn.setAttribute('aria-pressed', 'false');
            liveTailLastCount = 0;
            liveTailErrors = 0;
            liveTailIdleHits = 0;
            var label = btn.querySelector('[data-lb-live-tail-label]');
            if (label) label.textContent = 'Live tail';
            try { localStorage.removeItem(LIVE_TAIL_KEY); } catch (_) {}
        } else {
            startLiveTail(btn);
        }
    });

    // Pause on tab hidden, snap back on visible. Browsers throttle
    // background timers anyway, but cutting polls entirely is cheaper
    // and means we come back fresh instead of replaying a backlog.
    document.addEventListener('visibilitychange', function () {
        var btn = currentLiveTailButton();
        if (!btn) return;
        if (document.hidden) {
            if (liveTailTimer) { clearTimeout(liveTailTimer); liveTailTimer = null; }
            if (liveTailAbort && typeof liveTailAbort.abort === 'function') {
                try { liveTailAbort.abort(); } catch (_) {}
            }
            liveTailInFlight = false;
        } else {
            // Tab just became visible — reset idle counter so we
            // immediately poll rather than whatever long delay was
            // queued before.
            liveTailIdleHits = 0;
            var url = btn.getAttribute('data-lb-live-tail-json');
            if (url) scheduleLiveTailPoll(btn, url);
        }
    });

    // Pause when offline; resume once back online.
    window.addEventListener('offline', function () {
        if (liveTailTimer) { clearTimeout(liveTailTimer); liveTailTimer = null; }
    });
    window.addEventListener('online', function () {
        var btn = currentLiveTailButton();
        if (!btn) return;
        liveTailErrors = 0;
        liveTailIdleHits = 0;
        var url = btn.getAttribute('data-lb-live-tail-json');
        if (url) scheduleLiveTailPoll(btn, url);
    });

    // Clean up before the page navigates away — prevents a dangling
    // in-flight fetch from resolving against a torn-down page.
    window.addEventListener('pagehide', function () { stopLiveTail(); });
    window.addEventListener('beforeunload', function () { stopLiveTail(); });

    function initLiveTail() {
        var enabled = false;
        try { enabled = localStorage.getItem(LIVE_TAIL_KEY) === '1'; } catch (_) {}
        if (!enabled) return;
        var btn = document.querySelector('[data-lb-live-tail]');
        if (btn) startLiveTail(btn);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLiveTail);
    } else {
        initLiveTail();
    }

    // ------------------------------------------------------
    // 6. Utility page: saved filter presets
    // ------------------------------------------------------
    // Each `.lb-preset` carries `data-lb-preset="<scope>"` — a
    // short key ("system", "audit") that namespaces presets per
    // page. Presets are stored as a JSON array in localStorage
    // under `statamic-logbook.presets.<scope>`. Each entry is
    // { name: string, query: string, createdAt: number }.
    //
    // The menu is closed on outside click and on Escape.
    var PRESET_KEY_PREFIX = 'statamic-logbook.presets.';

    function readPresets(scope) {
        try {
            var raw = localStorage.getItem(PRESET_KEY_PREFIX + scope);
            if (!raw) return [];
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) { return []; }
    }

    function writePresets(scope, list) {
        try { localStorage.setItem(PRESET_KEY_PREFIX + scope, JSON.stringify(list)); } catch (_) {}
    }

    function renderPresetList(root) {
        var scope = root.getAttribute('data-lb-preset') || 'default';
        var list = readPresets(scope);
        // `.lb-preset__menu` (and therefore `[data-lb-preset-list]`) may be
        // portalled into <body> while open — look inside the root first,
        // then fall back to the portalled menu by id.
        var host = root.querySelector('[data-lb-preset-list]');
        if (!host) {
            var menuId = root.getAttribute('data-lb-preset-menu-id');
            if (menuId) {
                var menu = document.getElementById(menuId);
                if (menu) host = menu.querySelector('[data-lb-preset-list]');
            }
        }
        if (!host) return;

        if (list.length === 0) {
            host.innerHTML = '<p class="lb-preset__empty">No saved presets yet.</p>';
            return;
        }

        host.innerHTML = list.map(function (p, i) {
            var safeName = (p.name || 'Preset').replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]);
            });
            return (
                '<button type="button" class="lb-preset__item" data-lb-preset-apply="' + i + '">' +
                    '<span>' + safeName + '</span>' +
                    '<button type="button" class="lb-preset__item-x" data-lb-preset-delete="' + i + '" aria-label="Delete preset">✕</button>' +
                '</button>'
            );
        }).join('');
    }

    // NOTE: the preset toolbar lives inside `.lb-filter--sticky`, which uses
    // `backdrop-filter: blur(8px)`. Per the CSS spec, `backdrop-filter`
    // creates a containing block for `position: fixed` descendants — so a
    // `position: fixed` menu left inside the toolbar is positioned relative
    // to the toolbar, not the viewport. We work around that by portalling
    // the menu element into `<body>` while it's open, and returning it to
    // its original parent on close. The `data-open="true"` CSS rule still
    // lives on `.lb-preset` (the toolbar-side root), so we apply it to the
    // portalled menu directly via an inline `display: block` override.
    function findMenu(root) {
        var id = root.getAttribute('data-lb-preset-menu-id');
        if (id) {
            var byId = document.getElementById(id);
            if (byId) return byId;
        }
        return root.querySelector('[data-lb-preset-menu]');
    }

    function portalMenu(root) {
        var menu = root.querySelector('[data-lb-preset-menu]');
        if (!menu) return null;
        // Assign a stable id so we can find the menu again once it's detached.
        var id = menu.id;
        if (!id) {
            id = 'lb-preset-menu-' + Math.random().toString(36).slice(2, 8);
            menu.id = id;
        }
        root.setAttribute('data-lb-preset-menu-id', id);
        // Remember the original parent so we can restore it on close.
        menu.__lbOriginalParent = menu.parentNode;
        menu.__lbOriginalNext = menu.nextSibling;
        document.body.appendChild(menu);
        return menu;
    }

    function restoreMenu(root) {
        var menu = findMenu(root);
        if (!menu) return;
        var parent = menu.__lbOriginalParent;
        if (parent) {
            parent.insertBefore(menu, menu.__lbOriginalNext || null);
        }
        menu.style.display = '';
        menu.style.top = '';
        menu.style.left = '';
    }

    function positionPresetMenu(root) {
        var toggle = root.querySelector('[data-lb-preset-toggle]');
        var menu = findMenu(root);
        if (!toggle || !menu) return;
        var r = toggle.getBoundingClientRect();
        // Ensure the menu is measurable even before CSS flips it on.
        menu.style.display = 'block';
        var menuWidth = Math.max(menu.offsetWidth, 260);
        var vw = document.documentElement.clientWidth || window.innerWidth;
        var gap = 6;
        // Prefer aligning the menu's right edge with the toggle's right edge.
        var left = Math.min(r.right - menuWidth, vw - menuWidth - 8);
        if (left < 8) left = 8;
        menu.style.top = (r.bottom + gap) + 'px';
        menu.style.left = left + 'px';
    }

    function openPreset(root, open) {
        var toggle = root.querySelector('[data-lb-preset-toggle]');
        if (open) {
            portalMenu(root);
            root.setAttribute('data-open', 'true');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
            positionPresetMenu(root);
        } else {
            root.setAttribute('data-open', 'false');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            restoreMenu(root);
        }
    }

    // Keep the menu anchored to its toggle when the user scrolls or resizes.
    window.addEventListener('scroll', function () {
        document.querySelectorAll('[data-lb-preset][data-open="true"]').forEach(positionPresetMenu);
    }, true);
    window.addEventListener('resize', function () {
        document.querySelectorAll('[data-lb-preset][data-open="true"]').forEach(positionPresetMenu);
    });

    // Initial render + close-on-outside-click handler.
    function initPresets() {
        document.querySelectorAll('[data-lb-preset]').forEach(renderPresetList);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPresets);
    } else {
        initPresets();
    }

    // Walk up from `el` to its `.lb-preset` root — either via DOM ancestry
    // (normal case) or by matching `data-lb-preset-menu-id` when the menu
    // has been portalled into <body>.
    function rootForEvent(el) {
        if (!el || !el.closest) return null;
        var direct = el.closest('[data-lb-preset]');
        if (direct) return direct;
        var menu = el.closest('[data-lb-preset-menu]');
        if (!menu || !menu.id) return null;
        return document.querySelector('[data-lb-preset-menu-id="' + menu.id + '"]');
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest && e.target.closest('[data-lb-preset-toggle]');
        if (toggle) {
            e.preventDefault();
            var root = toggle.closest('[data-lb-preset]');
            document.querySelectorAll('[data-lb-preset]').forEach(function (r) { if (r !== root) openPreset(r, false); });
            openPreset(root, root.getAttribute('data-open') !== 'true');
            return;
        }

        var applyBtn = e.target.closest && e.target.closest('[data-lb-preset-apply]');
        if (applyBtn && !e.target.closest('[data-lb-preset-delete]')) {
            e.preventDefault();
            var root = rootForEvent(applyBtn);
            if (!root) return;
            var scope = root.getAttribute('data-lb-preset') || 'default';
            var list = readPresets(scope);
            var i = Number(applyBtn.getAttribute('data-lb-preset-apply'));
            var preset = list[i];
            if (preset && preset.query !== undefined) {
                window.location.search = preset.query || '';
            }
            return;
        }

        var deleteBtn = e.target.closest && e.target.closest('[data-lb-preset-delete]');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            var root = rootForEvent(deleteBtn);
            if (!root) return;
            var scope = root.getAttribute('data-lb-preset') || 'default';
            var list = readPresets(scope);
            var i = Number(deleteBtn.getAttribute('data-lb-preset-delete'));
            if (i >= 0 && i < list.length) {
                list.splice(i, 1);
                writePresets(scope, list);
                renderPresetList(root);
            }
            return;
        }

        var saveBtn = e.target.closest && e.target.closest('[data-lb-preset-save]');
        if (saveBtn) {
            e.preventDefault();
            var root = rootForEvent(saveBtn);
            if (!root) return;
            var scope = root.getAttribute('data-lb-preset') || 'default';
            var suggested = scope.charAt(0).toUpperCase() + scope.slice(1) + ' · ' + new Date().toLocaleDateString();
            var name;
            try { name = window.prompt('Name this preset', suggested); } catch (_) { name = suggested; }
            if (!name) return;
            var query = window.location.search.replace(/^\?/, '');
            var list = readPresets(scope);
            list.push({ name: name, query: query, createdAt: Date.now() });
            writePresets(scope, list);
            renderPresetList(root);
            openPreset(root, false); // close menu so user sees the save
            return;
        }

        // Clicks outside any preset root/menu close open menus.
        document.querySelectorAll('[data-lb-preset][data-open="true"]').forEach(function (r) {
            var inRoot = e.target.closest && e.target.closest('[data-lb-preset]');
            var inMenu = e.target.closest && e.target.closest('[data-lb-preset-menu]');
            if (!inRoot && !inMenu) openPreset(r, false);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('[data-lb-preset][data-open="true"]').forEach(function (r) { openPreset(r, false); });
    });

    // ------------------------------------------------------
    // 6b. Utility page: filter form — strip empty params on submit
    // ------------------------------------------------------
    // Disables empty <input>/<select> controls before the browser
    // serializes the form so the resulting URL is `?level=error`
    // instead of `?from=&to=&level=error&channel=&q=`. Empty values
    // round-trip into the model anyway, but a clean URL is easier to
    // share and to spot in the address bar.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches || !form.matches('[data-lb-filter-form]')) return;

        var controls = form.querySelectorAll('input[name], select[name]');
        controls.forEach(function (el) {
            // Skip type=hidden — those carry preserved state (sort/dir).
            if (el.type === 'hidden') return;
            var value = el.value;
            if (value === '' || value === null) {
                el.disabled = true; // disabled controls aren't submitted
            }
        });
    });

    // ------------------------------------------------------
    // 6c. Utility page: per-page selector — auto-submit on change
    // ------------------------------------------------------
    // The footer form lives outside the main filter form (so changing
    // page size doesn't reset the user's unsaved filter inputs to
    // whatever is currently in the URL). We submit it immediately on
    // `change` so there's no "Apply" button to hunt for.
    document.addEventListener('change', function (e) {
        var select = e.target;
        if (!select || !select.matches || !select.matches('[data-lb-perpage-select]')) return;
        var form = select.closest('[data-lb-perpage-form]');
        if (!form) return;
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    // ------------------------------------------------------
    // 6d. Filter pills — visual toggle + auto-submit on change
    // ------------------------------------------------------
    // The Timeline view (and any future pages using the same idiom)
    // renders severity / type filters as:
    //
    //   <label class="lb-pill ...">
    //     <input type="checkbox" name="sev[]" value="error"> Errors
    //   </label>
    //
    // with the checkbox itself hidden via CSS and the label acting as
    // the clickable surface. Native label semantics toggle the nested
    // checkbox on click — we just need to:
    //
    //   1. Toggle the `.lb-pill--active` class on the label so the
    //      user gets immediate visual feedback (otherwise they'd have
    //      to wait for the server round-trip to see their choice land).
    //   2. Submit the enclosing `data-lb-filter-form` so the filter
    //      applies without a separate Apply click.
    //
    // Any element using `<label class="lb-pill"><input type="checkbox">`
    // inside a `data-lb-filter-form` automatically gets this behaviour;
    // no per-pill opt-in attribute is required.
    var lbPillSubmitTimer = null;
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input || !input.matches) return;
        if (!input.matches('input[type="checkbox"]')) return;

        var label = input.closest('label.lb-pill');
        if (!label) return;

        // 1. Immediate visual feedback.
        label.classList.toggle('lb-pill--active', input.checked);

        // 2. Auto-submit — only if the pill lives inside a filter form.
        var form = input.closest('form[data-lb-filter-form]');
        if (!form) return;

        // Debounce rapid toggles (e.g. quickly clicking several pills)
        // so we submit once after the last change lands. 80ms is below
        // human click cadence but above any event-loop jitter.
        if (lbPillSubmitTimer) {
            clearTimeout(lbPillSubmitTimer);
        }
        lbPillSubmitTimer = setTimeout(function () {
            lbPillSubmitTimer = null;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }, 80);
    });

    // ------------------------------------------------------
    // 7. Utility page: keyboard shortcuts
    // ------------------------------------------------------
    // `/`      → focus primary filter search input on the page
    // `g s`    → go to system logs
    // `g a`    → go to audit logs
    // Shortcuts are suppressed while typing in form fields.
    var gLeaderUntil = 0;

    function isEditable(el) {
        if (!el) return false;
        if (el.isContentEditable) return true;
        var tag = (el.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select';
    }

    document.addEventListener('keydown', function (e) {
        if (isEditable(e.target)) return;
        if (e.metaKey || e.ctrlKey || e.altKey) return;

        // `/` → focus search
        if (e.key === '/') {
            var search = document.querySelector('.lb-page .lb-filter__search');
            if (search) {
                e.preventDefault();
                search.focus();
                search.select && search.select();
            }
            return;
        }

        // `g <k>` leader sequence
        var now = Date.now();
        if (e.key === 'g') {
            gLeaderUntil = now + 1200;
            return;
        }
        if (now < gLeaderUntil) {
            var systemLink = document.querySelector('.lb-tabs a.lb-tab[href*="/logbook/system"]');
            var auditLink  = document.querySelector('.lb-tabs a.lb-tab[href*="/logbook/audit"]');
            if (e.key === 's' && systemLink) { e.preventDefault(); window.location.href = systemLink.getAttribute('href'); }
            if (e.key === 'a' && auditLink)  { e.preventDefault(); window.location.href = auditLink.getAttribute('href'); }
            gLeaderUntil = 0;
        }
    });

    function openModal(title, payloadB64, subtitle) {
        var modal = document.getElementById('logbook-modal');
        if (!modal) return;
        setText('logbook-modal-title', title);
        setText('logbook-modal-subtitle', subtitle);
        var text = payloadB64 ? decodeB64(payloadB64) : '—';
        setText('logbook-modal-body', text);
        modal.classList.remove('lb-hidden');
        document.body.classList.add('lb-no-scroll');
    }

    function closeModal() {
        var modal = document.getElementById('logbook-modal');
        if (!modal) return;
        modal.classList.add('lb-hidden');
        document.body.classList.remove('lb-no-scroll');
    }

    function copyModal() {
        var body = document.getElementById('logbook-modal-body');
        if (!body) return;
        var text = body.textContent || '';
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(
                function () { toast('info', 'Copied to clipboard'); },
                function () { legacyCopy(text); }
            );
        } else {
            legacyCopy(text);
        }
    }

    function legacyCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            toast('info', 'Copied to clipboard');
        } catch (_) {
            toast('error', 'Copy failed');
        }
        document.body.removeChild(ta);
    }

    // ------------------------------------------------------
    // Helpers
    // ------------------------------------------------------
    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text || '';
    }

    function decodeB64(b64) {
        try {
            var binary = atob(b64);
            var bytes = Uint8Array.from(binary, function (c) { return c.charCodeAt(0); });
            return new TextDecoder('utf-8').decode(bytes);
        } catch (_) {
            return '';
        }
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function toast(type, text) {
        try {
            if (window.Statamic && window.Statamic.$toast) {
                var t = window.Statamic.$toast;
                if (type === 'success' && typeof t.success === 'function') return t.success(text);
                if (type === 'error' && typeof t.error === 'function') return t.error(text);
                if (typeof t.info === 'function') return t.info(text);
                if (typeof t.show === 'function') return t.show(text);
            }
            if (window.$toast) {
                var u = window.$toast;
                if (type === 'success' && typeof u.success === 'function') return u.success(text);
                if (type === 'error' && typeof u.error === 'function') return u.error(text);
                if (typeof u.info === 'function') return u.info(text);
            }
        } catch (_) {
            // fall through
        }
        if (type === 'error') {
            // Only interrupt the user for errors; info/success fall back to console.
            try { alert(text); } catch (_) { /* noop */ }
        } else {
            try { console.info('[logbook]', text); } catch (_) { /* noop */ }
        }
    }
})();
