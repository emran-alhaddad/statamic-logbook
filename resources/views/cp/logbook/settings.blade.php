{{--
    Logbook — Settings + first-run database setup
    ---------------------------------------------
    Two modes:
      - Not configured  → DB setup screen: connection fields prefilled from
        .env (falling back to the app's own database), editable, one-click
        table install. No other questions — capture starts with sensible
        defaults and is tuned here afterwards.
      - Configured      → capture settings, structured the way operators
        think: System Logs (with its levels), Audit Logs (per CMS
        component, each showing its actual events), housekeeping.

    Persisted via SettingsRepository (DB) + EnvWriter (.env, DB creds only).
--}}
@extends('statamic-logbook::cp.logbook._layout', ['active' => 'settings'])

@section('panel')

@php
    $card = 'margin-bottom: var(--lb-s-4); padding: var(--lb-s-4); border: 1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius: 10px;';
    $sub  = 'lb-toolbar__sub';
@endphp

@if($saved)
    <div role="status" style="margin-bottom: var(--lb-s-4); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.08);">
        ✓ Saved — takes effect immediately.
    </div>
@endif

@if($setupError !== '')
    <div role="alert" style="margin-bottom: var(--lb-s-4); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(239,68,68,.4); background: rgba(239,68,68,.08);">
        {{ $setupError }}
    </div>
@endif

@if($installOutput !== '')
    <div role="status" style="margin-bottom: var(--lb-s-4); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(99,102,241,.35); background: rgba(99,102,241,.08);">
        <pre style="white-space: pre-wrap; font-size: 12px; margin:0;">{{ $installOutput }}</pre>
    </div>
@endif

@unless($configured)
    {{-- ======================= FIRST-RUN: DATABASE ======================= --}}
    <div style="max-width: 640px; margin: 0 auto; padding: var(--lb-s-6) 0;">
        <div style="text-align:center; margin-bottom: var(--lb-s-5);">
            <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 6px;">Set up Logbook</h2>
            <p class="{{ $sub }}">
                Logbook keeps its logs in a database. These fields are prefilled from your
                <code>.env</code> — keep them to use your app's database, or point at a separate one.
            </p>
        </div>

        <form method="POST" action="{{ cp_route('utilities.logbook.settings.database') }}">
            @csrf
            <div style="{{ $card }}">
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <label>
                        <span style="font-weight:600;">Host</span>
                        <input type="text" name="host" value="{{ $db['host'] }}" class="lb-input" style="width:100%; margin-top:4px;">
                    </label>
                    <label>
                        <span style="font-weight:600;">Port</span>
                        <input type="text" name="port" value="{{ $db['port'] }}" class="lb-input" style="width:100%; margin-top:4px;">
                    </label>
                </div>
                <label style="display:block; margin-bottom:10px;">
                    <span style="font-weight:600;">Database</span>
                    <input type="text" name="database" value="{{ $db['database'] }}" class="lb-input" style="width:100%; margin-top:4px;" required>
                </label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <label>
                        <span style="font-weight:600;">Username</span>
                        <input type="text" name="username" value="{{ $db['username'] }}" class="lb-input" style="width:100%; margin-top:4px;" required>
                    </label>
                    <label>
                        <span style="font-weight:600;">Password</span>
                        <input type="password" name="password" value="{{ $db['password'] }}" class="lb-input" style="width:100%; margin-top:4px;" autocomplete="new-password">
                    </label>
                </div>

                @unless($envWritable)
                    <p class="{{ $sub }}" style="margin-top:10px;">
                        ⚠ <code>.env</code> is not writable — settings will apply, but you'll need to add the
                        <code>LOGBOOK_DB_*</code> keys manually afterwards.
                    </p>
                @endunless
            </div>

            <div style="text-align:center;">
                <button type="submit" class="lb-btn" style="font-weight: 700; padding: 10px 24px;">
                    Connect &amp; install →
                </button>
                <p class="{{ $sub }}" style="margin-top:6px;">
                    Creates the Logbook tables and starts capturing with everything enabled.
                    Fine-tune what's recorded right after.
                </p>
            </div>
        </form>
    </div>
@else
    {{-- ============================ SETTINGS ============================ --}}
    <form method="POST" action="{{ cp_route('utilities.logbook.settings.save') }}" style="max-width: 900px;">
        @csrf

        {{-- ---------------- System logs ---------------- --}}
        <div style="{{ $card }}">
            <label style="display:flex; gap:10px; align-items:center; cursor:pointer; margin-bottom: 4px;">
                <input type="checkbox" name="system_logs" value="1" @checked($settings['system_logs']) data-lb-master="system">
                <span style="font-weight:700; font-size: 15px;">System Logs</span>
            </label>
            <p class="{{ $sub }}" style="margin-bottom: 12px;">
                Server-side application messages — errors, warnings, notices from Laravel, Statamic and your own code.
            </p>

            <div data-lb-section="system" style="display:flex; flex-wrap:wrap; gap: 8px 18px;">
                @foreach($levels as $level)
                    <label style="display:flex; gap:6px; align-items:center; cursor:pointer;">
                        <input type="checkbox" name="levels[{{ $level }}]" value="1" @checked($settings['system_levels'][$level] ?? true)>
                        <span style="text-transform: capitalize;">{{ $level }}</span>
                    </label>
                @endforeach
            </div>

            <label style="display:block; margin-top: 12px;">
                <span style="font-weight:600;">Ignored channels</span>
                <input type="text" name="ignore_channels_extra" value="{{ $settings['ignore_channels_extra'] }}"
                       placeholder="e.g. deprecations,queries" class="lb-input" style="width:100%; max-width:420px; margin-top:4px;">
                <span class="{{ $sub }}">Comma-separated log channels to skip, on top of the defaults.</span>
            </label>
        </div>

        {{-- ---------------- Audit logs ---------------- --}}
        <div style="{{ $card }}">
            <label style="display:flex; gap:10px; align-items:center; cursor:pointer; margin-bottom: 4px;">
                <input type="checkbox" name="audit_logs" value="1" @checked($settings['audit_logs']) data-lb-master="audit">
                <span style="font-weight:700; font-size: 15px;">Audit Logs</span>
            </label>
            <p class="{{ $sub }}" style="margin-bottom: 12px;">
                Who did what in the CMS. Untick a whole component, or open it and pick individual events.
            </p>

            <div data-lb-section="audit" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 10px;">
                @foreach($groups as $key => $group)
                    @php
                        $disabledSet = array_flip($settings['disabled_events']);
                        $enabledCount = collect($group['events'])->reject(fn ($e) => isset($disabledSet[$e['class']]))->count();
                        $total = count($group['events']);
                    @endphp
                    <details style="border:1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius:8px; padding: 8px 12px;" @if($enabledCount < $total) open @endif>
                        <summary style="cursor:pointer; display:flex; align-items:center; gap:8px; list-style: none;">
                            <input type="checkbox" data-lb-group="{{ $key }}"
                                   @checked($enabledCount === $total)
                                   onclick="event.stopPropagation();"
                                   title="Toggle all {{ $group['label'] }} events">
                            <span style="font-weight:600;">{{ $group['label'] }}</span>
                            <span class="{{ $sub }}" style="margin-left:auto;" data-lb-group-count="{{ $key }}">{{ $enabledCount }}/{{ $total }}</span>
                        </summary>
                        <div style="margin-top: 8px; display:flex; flex-direction:column; gap: 5px;">
                            @foreach($group['events'] as $event)
                                <label style="display:flex; gap:6px; align-items:center; cursor:pointer; font-size: 13px;">
                                    <input type="checkbox" name="events[]" value="{{ $event['class'] }}"
                                           data-lb-group-member="{{ $key }}"
                                           @checked(! isset($disabledSet[$event['class']]))>
                                    <span>{{ $event['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>

            <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer; margin-top: 12px;">
                <input type="checkbox" name="activity_views" value="1" @checked($settings['activity_views']) style="margin-top:3px;">
                <span><strong>Page views</strong><br>
                    <span class="{{ $sub }}">Also record who opened which entry, collection, user… (higher volume).</span>
                </span>
            </label>

            <label style="display:block; margin-top: 12px;">
                <span style="font-weight:600;">Extra ignored fields</span>
                <input type="text" name="ignore_fields_extra" value="{{ $settings['ignore_fields_extra'] }}"
                       placeholder="e.g. internal_notes,cache_key" class="lb-input" style="width:100%; max-width:420px; margin-top:4px;">
                <span class="{{ $sub }}">Comma-separated field names to leave out of change diffs.</span>
            </label>
        </div>

        {{-- ---------------- Housekeeping ---------------- --}}
        <div style="{{ $card }}">
            <p style="font-weight: 700; margin-bottom: 8px;">Housekeeping</p>
            <label>
                <span style="font-weight:600;">Keep logs for</span>
                <input type="number" name="retention_days" min="1" max="3650" value="{{ $settings['retention_days'] }}"
                       class="lb-input" style="width: 100px; margin: 0 6px;"> days
                <br><span class="{{ $sub }}">Older rows are removed by Prune.</span>
            </label>
            <p class="{{ $sub }}" style="margin-top: 10px;">
                Database: {{ $dbOk ? '✅ connected' : '⚠️ unreachable' }} · Tables: {{ $installed ? '✅ installed' : '⚠️ missing' }}
            </p>
        </div>

        <div style="display:flex; align-items:center; gap: 12px;">
            <button type="submit" class="lb-btn" style="font-weight: 700;">Save settings</button>
            <span class="{{ $sub }}">Stored in the Logbook database — applies on the next page load.</span>
        </div>
    </form>

    {{-- Group toggle behaviour: the group checkbox checks/unchecks its
         members; member changes update the group state + count. Utility
         pages render as normal Blade (unlike widgets), so an inline
         script is safe here. --}}
    <script>
    (function () {
        'use strict';
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
