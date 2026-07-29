@extends('statamic::layout')

@section('title', 'Logbook')

@section('content')
{{-- Inline icon symbols (referenced via <use>) --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<symbol id="lbi-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></symbol>
<symbol id="lbi-db" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></symbol>
<symbol id="lbi-term" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></symbol>
<symbol id="lbi-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1 1 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></symbol>
<symbol id="lbi-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></symbol>
<symbol id="lbi-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></symbol>
<symbol id="lbi-file" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></symbol>
<symbol id="lbi-folder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></symbol>
<symbol id="lbi-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.42 0l8.58-8.58a1 1 0 0 0 0-1.42z"/><circle cx="7" cy="7" r="1.5" fill="currentColor" stroke="none"/></symbol>
<symbol id="lbi-map" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></symbol>
<symbol id="lbi-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/></symbol>
<symbol id="lbi-img" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1 1 0 0 0-1.42 0L5 21"/></symbol>
<symbol id="lbi-layout" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></symbol>
<symbol id="lbi-inbox" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></symbol>
<symbol id="lbi-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
<symbol id="lbi-server" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></symbol>
<symbol id="lbi-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></symbol>
<symbol id="lbi-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
<symbol id="lbi-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></symbol>
<symbol id="lbi-act" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></symbol>
<symbol id="lbi-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></symbol>
<symbol id="lbi-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></symbol>
<symbol id="lbi-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></symbol>
<symbol id="lbi-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>
<symbol id="lbi-pencil" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></symbol>
<symbol id="lbi-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></symbol>
<symbol id="lbi-login" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></symbol>
<symbol id="lbi-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></symbol>
<symbol id="lbi-dot" viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="12" r="4"/></symbol>
</defs></svg>

<div class="lb-page" data-lb-scope>
    <div class="lb-toolbar">
        <div class="lb-toolbar__titles">
            <p class="lb-header__kicker">Utilities</p>
            <h1 class="lb-toolbar__title">Logbook</h1>
            <p class="lb-toolbar__sub">System logs &amp; user audit logs</p>
        </div>
        <div class="lb-toolbar__actions">
            {{-- Density toggle — applies at the page root so each mode
                 meaningfully restructures the panel (not just font size).
                 Compact hides secondary meta + forces single-line truncation.
                 Spacious releases truncation and wraps long content. --}}
            <div class="lb-density" role="group" aria-label="Row density">
                <button type="button" class="lb-density__btn" data-lb-density="compact" aria-pressed="false" title="Compact — dense analyst view, hides secondary meta">Compact</button>
                <button type="button" class="lb-density__btn" data-lb-density="cozy"    aria-pressed="true"  title="Cozy — default, balanced reading">Cozy</button>
                <button type="button" class="lb-density__btn" data-lb-density="spacious" aria-pressed="false" title="Spacious — wraps long content, larger controls">Spacious</button>
            </div>

            <button type="button"
                    class="lb-btn"
                    data-lb-command="prune"
                    data-lb-command-url="{{ cp_route('utilities.logbook.actions.prune') }}"
                    data-lb-command-label="Prune Logs"
                    data-lb-csrf="{{ csrf_token() }}"
                    title="Delete logs older than the configured retention window">
                Prune Logs
            </button>
            <button type="button"
                    class="lb-btn"
                    data-lb-command="flush-spool"
                    data-lb-command-url="{{ cp_route('utilities.logbook.actions.flush-spool') }}"
                    data-lb-command-label="Flush Spool"
                    data-lb-csrf="{{ csrf_token() }}"
                    title="Drain the on-disk spool into the database">
                Flush Spool
            </button>
        </div>
    </div>

    <div class="lb-box">
        @php
            /** Disabled streams disappear from the tab bar entirely. */
            $lbStreams = \EmranAlhaddad\StatamicLogbook\Support\SettingsRepository::streams();
        @endphp
        <nav class="lb-tabs" aria-label="Logbook sections">
            @if($lbStreams['system'])
                <a href="{{ cp_route('utilities.logbook.system') }}"
                   class="lb-tab {{ $active === 'system' ? 'lb-tab--active' : '' }}">
                    System Logs
                </a>
            @endif
            @if($lbStreams['audit'])
                <a href="{{ cp_route('utilities.logbook.audit') }}"
                   class="lb-tab {{ $active === 'audit' ? 'lb-tab--active' : '' }}">
                    Audit Logs
                </a>
            @endif
            @if($lbStreams['system'] || $lbStreams['audit'])
                <a href="{{ cp_route('utilities.logbook.timeline') }}"
                   class="lb-tab {{ $active === 'timeline' ? 'lb-tab--active' : '' }}">
                    Timeline
                </a>
            @endif
            @can('configure logbook')
                <a href="{{ cp_route('utilities.logbook.settings') }}"
                   class="lb-tab lb-tab--end {{ ($active ?? '') === 'settings' ? 'lb-tab--active' : '' }}">
                    Settings
                </a>
            @endcan
        </nav>

        <div class="lb-panel-body" v-pre>
            @yield('panel')
        </div>
    </div>

    <div id="logbook-modal" class="lb-hidden" role="dialog" aria-modal="true" aria-labelledby="logbook-modal-title">
        <div class="lb-modal__backdrop" data-lb-modal-close></div>
        <div class="lb-modal__dialog">
            <div class="lb-modal__header">
                <div class="lb-modal__titles">
                    <p id="logbook-modal-title" class="lb-modal__title">Details</p>
                    <p id="logbook-modal-subtitle" class="lb-modal__subtitle"></p>
                </div>
                <div class="lb-modal__actions">
                    <button type="button" class="lb-btn lb-btn--sm" data-lb-modal-copy title="Copy to clipboard">Copy</button>
                    <button type="button" class="lb-btn lb-btn--sm" data-lb-modal-close title="Close (Esc)">
                        Close <span class="lb-kbd" aria-hidden="true">Esc</span>
                    </button>
                </div>
            </div>
            <pre id="logbook-modal-body" class="lb-modal__body"></pre>
        </div>
    </div>
</div>
@endsection
