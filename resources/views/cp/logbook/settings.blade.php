{{--
    Logbook — Settings + first-run onboarding
    -----------------------------------------
    One view, two modes:
      - Not yet configured  → onboarding wizard (DB status, table install,
        preset picker). Friendly first-run for fresh composer installs.
      - Configured          → full settings form (master switches, event
        groups, activity tracking, retention, advanced).

    Everything persists to the logbook DB via SettingsRepository — no .env
    edits needed. Styling reuses the addon-shipped lb-* classes.
--}}
@extends('statamic-logbook::cp.logbook._layout', ['active' => 'settings'])

@section('panel')

@if($saved)
    <div class="lb-callout lb-callout--ok" role="status"
         style="margin-bottom: var(--lb-s-4); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.08);">
        ✓ Settings saved — they take effect immediately.
    </div>
@endif

@if($installOutput !== '')
    <div class="lb-callout" role="status"
         style="margin-bottom: var(--lb-s-4); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(99,102,241,.35); background: rgba(99,102,241,.08);">
        <p style="font-weight:600; margin-bottom:4px;">Install output</p>
        <pre style="white-space: pre-wrap; font-size: 12px; margin:0;">{{ $installOutput }}</pre>
    </div>
@endif

@unless($configured)
    {{-- ============================ ONBOARDING ============================ --}}
    <div style="max-width: 720px; margin: 0 auto; padding: var(--lb-s-6) 0;">
        <div style="text-align:center; margin-bottom: var(--lb-s-6);">
            <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 6px;">👋 Welcome to Logbook</h2>
            <p class="lb-toolbar__sub">Let's get your audit trail running — three quick steps, no .env editing.</p>
        </div>

        {{-- Step 1: database --}}
        <div class="lb-panel" style="margin-bottom: var(--lb-s-4); padding: var(--lb-s-4); border: 1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius: 10px;">
            <p style="font-weight: 700; margin-bottom: 8px;">1 · Database</p>
            @if($dbOk && $installed)
                <p>✅ Connected, and all Logbook tables exist. Nothing to do here.</p>
            @elseif($dbOk && ! $installed)
                <p style="margin-bottom: 10px;">🔌 Database connection works, but the Logbook tables haven't been created yet.</p>
                <form method="POST" action="{{ cp_route('utilities.logbook.settings.install') }}">
                    @csrf
                    <button type="submit" class="lb-btn">Create tables now</button>
                </form>
            @else
                <p style="margin-bottom: 6px;">⚠️ Can't reach the Logbook database.</p>
                <p class="lb-toolbar__sub" style="margin-bottom: 6px;">
                    Logbook stores logs in a database configured via <code>LOGBOOK_DB_*</code> in your <code>.env</code>
                    (it can reuse your app's database). This is the only thing that must live in .env — everything else
                    is managed right here.
                </p>
                @if($dbError)
                    <pre style="white-space: pre-wrap; font-size: 11px; opacity:.7;">{{ $dbError }}</pre>
                @endif
                <form method="POST" action="{{ cp_route('utilities.logbook.settings.install') }}" style="margin-top: 8px;">
                    @csrf
                    <button type="submit" class="lb-btn">Retry / create tables</button>
                </form>
            @endif
        </div>

        {{-- Step 2 + 3: preset & go --}}
        <form method="POST" action="{{ cp_route('utilities.logbook.settings.onboard') }}">
            @csrf
            <div class="lb-panel" style="margin-bottom: var(--lb-s-4); padding: var(--lb-s-4); border: 1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius: 10px;">
                <p style="font-weight: 700; margin-bottom: 8px;">2 · What should Logbook capture?</p>
                <p class="lb-toolbar__sub" style="margin-bottom: 12px;">Pick a starting point — you can fine-tune everything in Settings later.</p>

                @foreach($presets as $key => $preset)
                    <label style="display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius: 8px; margin-bottom: 8px; cursor: pointer;">
                        <input type="radio" name="preset" value="{{ $key }}" @checked($key === 'recommended') style="margin-top: 3px;">
                        <span>
                            <span style="font-weight: 600;">{{ $preset['label'] }}</span>
                            @if($key === 'recommended')
                                <span class="lb-chip lb-chip--create" style="margin-left: 6px;">Suggested</span>
                            @endif
                            <br>
                            <span class="lb-toolbar__sub">{{ $preset['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <div style="text-align: center;">
                <button type="submit" class="lb-btn" style="font-weight: 700; padding: 10px 22px;" @disabled(! $installed)>
                    3 · Start logging →
                </button>
                @unless($installed)
                    <p class="lb-toolbar__sub" style="margin-top: 6px;">Create the tables above first.</p>
                @endunless
            </div>
        </form>
    </div>
@else
    {{-- ============================= SETTINGS ============================= --}}
    <form method="POST" action="{{ cp_route('utilities.logbook.settings.save') }}" style="max-width: 860px;">
        @csrf

        {{-- Master switches --}}
        <div class="lb-panel" style="margin-bottom: var(--lb-s-4); padding: var(--lb-s-4); border: 1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius: 10px;">
            <p style="font-weight: 700; margin-bottom: 4px;">Capture</p>
            <p class="lb-toolbar__sub" style="margin-bottom: 12px;">Master switches for each log stream.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px;">
                <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer;">
                    <input type="checkbox" name="audit_logs" value="1" @checked($settings['audit_logs']) style="margin-top:3px;">
                    <span><strong>Audit log</strong><br><span class="lb-toolbar__sub">Who changed what — the core of Logbook.</span></span>
                </label>
                <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer;">
                    <input type="checkbox" name="system_logs" value="1" @checked($settings['system_logs']) style="margin-top:3px;">
                    <span><strong>System log</strong><br><span class="lb-toolbar__sub">Application log messages (errors, warnings…).</span></span>
                </label>
                <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer;">
                    <input type="checkbox" name="auth_events" value="1" @checked($settings['auth_events']) style="margin-top:3px;">
                    <span><strong>Sign-in activity</strong><br><span class="lb-toolbar__sub">Sign-ins, sign-outs, failed attempts, password resets.</span></span>
                </label>
                <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer;">
                    <input type="checkbox" name="activity_views" value="1" @checked($settings['activity_views']) style="margin-top:3px;">
                    <span><strong>Page views</strong><br><span class="lb-toolbar__sub">Who opened which entry, collection, user… (higher volume).</span></span>
                </label>
            </div>
        </div>

        {{-- Event groups --}}
        <div class="lb-panel" style="margin-bottom: var(--lb-s-4); padding: var(--lb-s-4); border: 1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius: 10px;">
            <p style="font-weight: 700; margin-bottom: 4px;">Audited areas</p>
            <p class="lb-toolbar__sub" style="margin-bottom: 12px;">Toggle which parts of the CMS the audit log records.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px;">
                @foreach($groups as $key => $group)
                    <label style="display:flex; gap:8px; align-items:flex-start; padding:10px 12px; border:1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius:8px; cursor:pointer;">
                        <input type="checkbox" name="groups[{{ $key }}]" value="1" @checked($settings['groups'][$key] ?? true) style="margin-top:3px;">
                        <span>
                            <strong>{{ $group['label'] }}</strong><br>
                            <span class="lb-toolbar__sub">{{ $group['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Housekeeping --}}
        <div class="lb-panel" style="margin-bottom: var(--lb-s-4); padding: var(--lb-s-4); border: 1px solid var(--lb-border, rgba(128,128,128,.25)); border-radius: 10px;">
            <p style="font-weight: 700; margin-bottom: 4px;">Housekeeping</p>

            <div style="display:flex; flex-wrap:wrap; gap:20px; margin-top: 10px;">
                <label style="display:block;">
                    <span style="font-weight:600;">Keep logs for</span><br>
                    <input type="number" name="retention_days" min="1" max="3650"
                           value="{{ $settings['retention_days'] }}"
                           class="lb-input" style="width: 110px; margin-top: 4px;"> days
                    <br><span class="lb-toolbar__sub">Older rows are removed by Prune.</span>
                </label>

                <label style="display:block; flex:1; min-width: 260px;">
                    <span style="font-weight:600;">Extra ignored fields</span><br>
                    <input type="text" name="ignore_fields_extra"
                           value="{{ $settings['ignore_fields_extra'] }}"
                           placeholder="e.g. internal_notes,cache_key"
                           class="lb-input" style="width:100%; margin-top:4px;">
                    <span class="lb-toolbar__sub">Comma-separated field names to leave out of change diffs.</span>
                </label>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap: 12px;">
            <button type="submit" class="lb-btn" style="font-weight: 700;">Save settings</button>
            <span class="lb-toolbar__sub">Stored in the Logbook database — no .env changes, applies on the next page load.</span>
        </div>
    </form>

    {{-- DB status footer --}}
    <div style="margin-top: var(--lb-s-6); padding-top: var(--lb-s-4); border-top: 1px solid var(--lb-border, rgba(128,128,128,.2));">
        <p class="lb-toolbar__sub">
            Database: {{ $dbOk ? '✅ connected' : '⚠️ unreachable' }} ·
            Tables: {{ $installed ? '✅ installed' : '⚠️ missing' }}
        </p>
        @unless($installed && $dbOk)
            <form method="POST" action="{{ cp_route('utilities.logbook.settings.install') }}" style="margin-top: 6px;">
                @csrf
                <button type="submit" class="lb-btn lb-btn--sm">Repair / create tables</button>
            </form>
        @endunless
    </div>
@endunless

@endsection
