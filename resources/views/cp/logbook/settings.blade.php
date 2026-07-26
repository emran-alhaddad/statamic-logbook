{{--
    Logbook — Settings + first-run database setup
    ---------------------------------------------
    Two modes:
      - Not configured → centered DB setup card, prefilled from .env.
      - Configured     → sectioned settings: System Logs (master switch +
        level chips), Audit Logs (master switch + per-component group
        cards expanding into event checklists), Housekeeping.

    All styling comes from the addon stylesheet (lb-settings-*, lb-switch,
    lb-level, lb-group, lb-check, lb-callout, lb-setup — see the
    "Settings & first-run setup" block in resources/dist/statamic-logbook.css).
--}}
@extends('statamic-logbook::cp.logbook._layout', ['active' => 'settings'])

@section('panel')

@if($saved)
    <div class="lb-callout lb-callout--ok" role="status">
        <span class="lb-callout__icon" aria-hidden="true">✓</span>
        <span>Settings saved. They apply from the next page load.</span>
    </div>
@endif

@if($setupError !== '')
    <div class="lb-callout lb-callout--danger" role="alert">
        <span class="lb-callout__icon" aria-hidden="true">✕</span>
        <span>{{ $setupError }}</span>
    </div>
@endif

@if($installOutput !== '')
    <div class="lb-callout lb-callout--info" role="status">
        <pre>{{ $installOutput }}</pre>
    </div>
@endif

@unless($configured)
    {{-- ======================= FIRST-RUN: DATABASE ======================= --}}
    <div class="lb-setup">
        <div class="lb-setup__head">
            <div class="lb-setup__mark" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="8" ry="3"/>
                    <path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/>
                    <path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3"/>
                </svg>
            </div>
            <h2 class="lb-setup__title">Connect Logbook to a database</h2>
            <p class="lb-setup__sub">
                Prefilled from your <code>.env</code> — keep it to log into your app's
                database, or point at a separate one.
            </p>
        </div>

        <form method="POST" action="{{ cp_route('utilities.logbook.settings.database') }}">
            @csrf
            <div class="lb-setup__card">
                <div class="lb-field-row lb-field-row--host">
                    <div class="lb-field">
                        <label class="lb-field__label" for="lb-db-host">Host</label>
                        <input id="lb-db-host" type="text" name="host" value="{{ $db['host'] }}" class="lb-input">
                    </div>
                    <div class="lb-field">
                        <label class="lb-field__label" for="lb-db-port">Port</label>
                        <input id="lb-db-port" type="text" name="port" value="{{ $db['port'] }}" class="lb-input">
                    </div>
                </div>

                <div class="lb-field">
                    <label class="lb-field__label" for="lb-db-database">Database</label>
                    <input id="lb-db-database" type="text" name="database" value="{{ $db['database'] }}" class="lb-input" required>
                </div>

                <div class="lb-field-row lb-field-row--split">
                    <div class="lb-field">
                        <label class="lb-field__label" for="lb-db-username">Username</label>
                        <input id="lb-db-username" type="text" name="username" value="{{ $db['username'] }}" class="lb-input" required>
                    </div>
                    <div class="lb-field">
                        <label class="lb-field__label" for="lb-db-password">Password</label>
                        <input id="lb-db-password" type="password" name="password" value="{{ $db['password'] }}" class="lb-input" autocomplete="new-password">
                    </div>
                </div>

                @unless($envWritable)
                    <p class="lb-field__help">
                        ⚠ <code>.env</code> is not writable — the connection will work now, but add the
                        <code>LOGBOOK_DB_*</code> keys manually to keep it after a restart.
                    </p>
                @endunless
            </div>

            <div class="lb-setup__actions">
                <button type="submit" class="lb-btn lb-btn--primary">Connect &amp; install</button>
                <p class="lb-setup__hint">
                    Creates the Logbook tables and starts capturing everything.
                    Fine-tune what's recorded right after.
                </p>
            </div>
        </form>
    </div>
@else
    {{-- ============================ SETTINGS ============================ --}}
    <form method="POST" action="{{ cp_route('utilities.logbook.settings.save') }}" class="lb-settings" data-lb-settings>
        @csrf

        {{-- System logs --}}
        <section class="lb-settings-section {{ $settings['system_logs'] ? '' : 'is-off' }}" data-lb-section>
            <header class="lb-settings-section__head">
                <label class="lb-switch">
                    <input type="checkbox" name="system_logs" value="1" @checked($settings['system_logs']) data-lb-section-master
                           aria-label="Capture system logs">
                    <span class="lb-switch__track"></span>
                    <span class="lb-switch__knob"></span>
                </label>
                <div class="lb-settings-section__titles">
                    <h3 class="lb-settings-section__title">System Logs</h3>
                    <p class="lb-settings-section__sub">Application messages from Laravel, Statamic and your own code.</p>
                </div>
            </header>
            <div class="lb-settings-section__body">
                <div class="lb-field">
                    <span class="lb-field__label">Captured levels</span>
                    <div class="lb-levels">
                        @foreach($levels as $level)
                            <label class="lb-level lb-level--{{ $level }}">
                                <input type="checkbox" name="levels[{{ $level }}]" value="1" @checked($settings['system_levels'][$level] ?? true)>
                                <span class="lb-level__dot" aria-hidden="true"></span>
                                {{ $level }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="lb-field">
                    <label class="lb-field__label" for="lb-ignore-channels">Ignored channels</label>
                    <input id="lb-ignore-channels" type="text" name="ignore_channels_extra"
                           value="{{ $settings['ignore_channels_extra'] }}"
                           placeholder="e.g. deprecations, queries" class="lb-input lb-field-md">
                    <p class="lb-field__help">Comma-separated log channels to skip, on top of the defaults.</p>
                </div>
            </div>
        </section>

        {{-- Audit logs --}}
        <section class="lb-settings-section {{ $settings['audit_logs'] ? '' : 'is-off' }}" data-lb-section>
            <header class="lb-settings-section__head">
                <label class="lb-switch">
                    <input type="checkbox" name="audit_logs" value="1" @checked($settings['audit_logs']) data-lb-section-master
                           aria-label="Capture audit logs">
                    <span class="lb-switch__track"></span>
                    <span class="lb-switch__knob"></span>
                </label>
                <div class="lb-settings-section__titles">
                    <h3 class="lb-settings-section__title">Audit Logs</h3>
                    <p class="lb-settings-section__sub">Who did what in the CMS — switch off a whole component, or open it and pick events.</p>
                </div>
            </header>
            <div class="lb-settings-section__body">
                <div class="lb-groups">
                    @php $disabledSet = array_flip($settings['disabled_events']); @endphp
                    @foreach($groups as $key => $group)
                        @php
                            $total = count($group['events']);
                            $enabledCount = collect($group['events'])->reject(fn ($e) => isset($disabledSet[$e['class']]))->count();
                        @endphp
                        <details class="lb-group" @if($enabledCount > 0 && $enabledCount < $total) open @endif>
                            <summary class="lb-group__summary">
                                <label class="lb-check lb-check--all" onclick="event.stopPropagation();">
                                    <input type="checkbox" data-lb-group="{{ $key }}"
                                           @checked($enabledCount === $total)
                                           aria-label="Toggle all {{ $group['label'] }} events">
                                </label>
                                <span class="lb-group__name">{{ $group['label'] }}</span>
                                <span class="lb-group__count" data-lb-group-count="{{ $key }}">{{ $enabledCount }}/{{ $total }}</span>
                                <svg class="lb-group__chev" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                    <path d="M4.5 2.5L8 6l-3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </summary>
                            <div class="lb-group__events">
                                @foreach($group['events'] as $event)
                                    <label class="lb-check">
                                        <input type="checkbox" name="events[]" value="{{ $event['class'] }}"
                                               data-lb-group-member="{{ $key }}"
                                               @checked(! isset($disabledSet[$event['class']]))>
                                        {{ $event['label'] }}
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>

                <label class="lb-check">
                    <input type="checkbox" name="activity_views" value="1" @checked($settings['activity_views'])>
                    <span>
                        <strong>Page views</strong>
                        <span class="lb-field__help">&nbsp;— also record who opened which entry, collection, user… (higher volume)</span>
                    </span>
                </label>

                <div class="lb-field">
                    <label class="lb-field__label" for="lb-ignore-fields">Ignored fields</label>
                    <input id="lb-ignore-fields" type="text" name="ignore_fields_extra"
                           value="{{ $settings['ignore_fields_extra'] }}"
                           placeholder="e.g. internal_notes, cache_key" class="lb-input lb-field-md">
                    <p class="lb-field__help">Comma-separated field names left out of change diffs, on top of the defaults.</p>
                </div>
            </div>
        </section>

        {{-- Housekeeping --}}
        <section class="lb-settings-section">
            <header class="lb-settings-section__head">
                <div class="lb-settings-section__titles">
                    <h3 class="lb-settings-section__title">Housekeeping</h3>
                    <p class="lb-settings-section__sub">Retention and storage status.</p>
                </div>
            </header>
            <div class="lb-settings-section__body">
                <div class="lb-field">
                    <label class="lb-field__label" for="lb-retention">Keep logs for</label>
                    <div class="lb-row">
                        <input id="lb-retention" type="number" name="retention_days" min="1" max="3650"
                               value="{{ $settings['retention_days'] }}" class="lb-input lb-field-sm">
                        <span class="lb-savebar__note">days — older rows are removed by Prune.</span>
                    </div>
                </div>

                <div class="lb-status-line">
                    <span class="lb-status-line__item">
                        <span class="lb-dot {{ $dbOk ? 'lb-dot--ok' : 'lb-dot--warn' }}" aria-hidden="true"></span>
                        Database {{ $dbOk ? 'connected' : 'unreachable' }}
                    </span>
                    <span class="lb-status-line__item">
                        <span class="lb-dot {{ $installed ? 'lb-dot--ok' : 'lb-dot--warn' }}" aria-hidden="true"></span>
                        Tables {{ $installed ? 'installed' : 'missing' }}
                    </span>
                </div>
            </div>
        </section>

        <div class="lb-savebar">
            <button type="submit" class="lb-btn lb-btn--primary">Save settings</button>
            <span class="lb-savebar__note">Stored in the Logbook database — no .env changes.</span>
        </div>
    </form>

    {{-- Group tri-state sync + section dimming. Utility pages render as
         normal Blade (unlike widgets), so an inline script is safe here. --}}
    <script>
    (function () {
        'use strict';

        // Master switches dim their section body.
        document.querySelectorAll('[data-lb-section]').forEach(function (section) {
            var master = section.querySelector('[data-lb-section-master]');
            if (!master) return;
            master.addEventListener('change', function () {
                section.classList.toggle('is-off', !master.checked);
            });
        });

        // Group checkbox ⇄ member checkboxes, with indeterminate + count.
        document.querySelectorAll('[data-lb-group]').forEach(function (groupBox) {
            var key = groupBox.getAttribute('data-lb-group');
            var members = document.querySelectorAll('[data-lb-group-member="' + key + '"]');
            var count = document.querySelector('[data-lb-group-count="' + key + '"]');

            function sync() {
                var on = 0;
                members.forEach(function (m) { if (m.checked) on++; });
                groupBox.checked = on === members.length;
                groupBox.indeterminate = on > 0 && on < members.length;
                if (count) count.textContent = on + '/' + members.length;
            }

            groupBox.addEventListener('change', function () {
                members.forEach(function (m) { m.checked = groupBox.checked; });
                sync();
            });
            members.forEach(function (m) { m.addEventListener('change', sync); });
            sync();
        });
    })();
    </script>
@endunless

@endsection
