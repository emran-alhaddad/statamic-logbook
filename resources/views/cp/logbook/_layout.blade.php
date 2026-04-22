@extends('statamic::layout')

@section('title', 'Logbook')

@section('content')
<div class="lb-page">
    <div class="lb-toolbar">
        <div class="lb-toolbar__titles">
            <h1 class="lb-toolbar__title">Logbook</h1>
            <p class="lb-toolbar__sub">System logs &amp; user audit logs</p>
        </div>
        <div class="lb-toolbar__actions">
            <button type="button"
                    class="lb-btn"
                    data-lb-command="prune"
                    data-lb-command-url="{{ cp_route('utilities.logbook.actions.prune') }}"
                    data-lb-command-label="Prune Logs"
                    data-lb-csrf="{{ csrf_token() }}">
                Prune Logs
            </button>
            <button type="button"
                    class="lb-btn"
                    data-lb-command="flush-spool"
                    data-lb-command-url="{{ cp_route('utilities.logbook.actions.flush-spool') }}"
                    data-lb-command-label="Flush Spool"
                    data-lb-csrf="{{ csrf_token() }}">
                Flush Spool
            </button>
        </div>
    </div>

    <div class="lb-box">
        <nav class="lb-tabs" aria-label="Logbook sections">
            <a href="{{ cp_route('utilities.logbook.system') }}"
               class="lb-tab {{ $active === 'system' ? 'lb-tab--active' : '' }}">
                System Logs
            </a>
            <a href="{{ cp_route('utilities.logbook.audit') }}"
               class="lb-tab {{ $active === 'audit' ? 'lb-tab--active' : '' }}">
                Audit Logs
            </a>
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
                    <button type="button" class="lb-btn" data-lb-modal-copy>Copy</button>
                    <button type="button" class="lb-btn" data-lb-modal-close>Close</button>
                </div>
            </div>
            <pre id="logbook-modal-body" class="lb-modal__body"></pre>
        </div>
    </div>
</div>
@endsection
