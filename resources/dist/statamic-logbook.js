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
    // Persists user's preference in localStorage and re-applies
    // on page load. Buttons carry `data-lb-density="<mode>"`.
    var DENSITY_KEY = 'statamic-logbook.density';
    var DENSITY_CLASSES = ['lb-table--compact', 'lb-table--spacious'];

    function applyDensity(mode) {
        var tables = document.querySelectorAll('.lb-page .lb-table');
        tables.forEach(function (t) {
            DENSITY_CLASSES.forEach(function (c) { t.classList.remove(c); });
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
        var mode = btn.getAttribute('data-lb-density') || 'comfortable';
        try { localStorage.setItem(DENSITY_KEY, mode); } catch (_) { /* ignore quota / private mode */ }
        applyDensity(mode);
    });

    // Re-apply stored preference on DOMContentLoaded
    function initDensity() {
        var mode = 'comfortable';
        try { mode = localStorage.getItem(DENSITY_KEY) || 'comfortable'; } catch (_) {}
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
    // The live-tail button carries:
    //   data-lb-live-tail-json="<JSON endpoint URL>"
    // When toggled on we poll the JSON endpoint every 5s with
    // the most-recent row id already in the table. If the
    // endpoint returns new rows, we flash the button label with
    // "N new — click to refresh" and the user can click once to
    // reload the page (preserves their filters via query string).
    //
    // Preference persists under localStorage key
    //   statamic-logbook.live-tail
    // so the tail resumes after a manual refresh.
    var LIVE_TAIL_KEY = 'statamic-logbook.live-tail';
    var liveTailTimer = null;
    var liveTailLastCount = 0;

    function latestIdOnPage() {
        // Tables are ordered desc on `id`, so the first data row wins.
        var firstTime = document.querySelector('.lb-page .lb-table tbody tr .lb-table__time');
        if (!firstTime) return 0;
        // id is not in the DOM, but the JSON endpoint accepts `after_id`
        // derived from the latest row's `created_at`. For simplicity we
        // instead ask the endpoint with after_id=0 and rely on its own
        // latest_id bookkeeping on subsequent polls.
        return Number(firstTime.getAttribute('data-lb-id') || 0);
    }

    function startLiveTail(button) {
        stopLiveTail(); // single-flight
        var url = button.getAttribute('data-lb-live-tail-json');
        if (!url) return;

        var baseId = Number(button.getAttribute('data-lb-live-tail-after') || 0);
        if (!baseId) {
            // Seed from an immediate probe so the first poll doesn't
            // return the currently-visible head row as "new".
            fetch(url + '?limit=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { latest_id: 0 }; })
                .then(function (j) {
                    baseId = Number(j.latest_id || 0);
                    button.setAttribute('data-lb-live-tail-after', String(baseId));
                    scheduleLiveTailPoll(button, baseId, url);
                })
                .catch(function () { scheduleLiveTailPoll(button, 0, url); });
        } else {
            scheduleLiveTailPoll(button, baseId, url);
        }

        button.setAttribute('aria-pressed', 'true');
        try { localStorage.setItem(LIVE_TAIL_KEY, '1'); } catch (_) {}
    }

    function scheduleLiveTailPoll(button, afterId, url) {
        liveTailTimer = setInterval(function () {
            fetch(url + '?after_id=' + encodeURIComponent(afterId), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    if (!j) return;
                    var count = Number(j.count || 0);
                    if (count > 0) {
                        liveTailLastCount += count;
                        afterId = Number(j.latest_id || afterId);
                        button.setAttribute('data-lb-live-tail-after', String(afterId));
                        var label = button.querySelector('[data-lb-live-tail-label]');
                        if (label) label.textContent = liveTailLastCount + ' new · click to refresh';
                    }
                })
                .catch(function () { /* swallow */ });
        }, 5000);
    }

    function stopLiveTail() {
        if (liveTailTimer) {
            clearInterval(liveTailTimer);
            liveTailTimer = null;
        }
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
            var label = btn.querySelector('[data-lb-live-tail-label]');
            if (label) label.textContent = 'Live tail';
            try { localStorage.removeItem(LIVE_TAIL_KEY); } catch (_) {}
        } else {
            startLiveTail(btn);
        }
    });

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
        var host = root.querySelector('[data-lb-preset-list]');
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

    function openPreset(root, open) {
        root.setAttribute('data-open', open ? 'true' : 'false');
        var toggle = root.querySelector('[data-lb-preset-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Initial render + close-on-outside-click handler.
    function initPresets() {
        document.querySelectorAll('[data-lb-preset]').forEach(renderPresetList);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPresets);
    } else {
        initPresets();
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
            var root = applyBtn.closest('[data-lb-preset]');
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
            var root = deleteBtn.closest('[data-lb-preset]');
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
            var root = saveBtn.closest('[data-lb-preset]');
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
            return;
        }

        // Clicks outside any preset root close open menus.
        document.querySelectorAll('[data-lb-preset][data-open="true"]').forEach(function (r) {
            if (!e.target.closest || !e.target.closest('[data-lb-preset]')) openPreset(r, false);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('[data-lb-preset][data-open="true"]').forEach(function (r) { openPreset(r, false); });
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
