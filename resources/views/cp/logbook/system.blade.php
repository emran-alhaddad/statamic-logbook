@extends('statamic-logbook::cp.logbook._layout', ['active' => 'system'])

@php
    $b64 = fn($v) => base64_encode((string) $v);

    $levelClass = fn($l) => match (strtolower((string) $l)) {
        'emergency', 'alert', 'critical', 'error' => 'lb-chip lb-chip--error',
        'warning'                                 => 'lb-chip lb-chip--warn',
        'notice', 'info'                          => 'lb-chip lb-chip--info',
        'debug'                                   => 'lb-chip lb-chip--debug',
        default                                   => 'lb-chip lb-chip--muted',
    };

    // Sort link builder — toggles direction when same column, else defaults desc
    $sort = $sort ?? 'id';
    $dir  = $dir  ?? 'desc';
    $sortLink = function (string $col) use ($sort, $dir, $filters) {
        $nextDir = ($sort === $col && $dir === 'desc') ? 'asc' : 'desc';
        $query   = array_filter(array_merge($filters ?? [], ['sort' => $col, 'dir' => $nextDir]));
        return cp_route('utilities.logbook.system').'?'.http_build_query($query);
    };
    $ariaSort = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';

    $humanTime = function ($value) {
        try {
            return \Carbon\Carbon::parse($value)->diffForHumans();
        } catch (\Throwable $e) {
            return $value;
        }
    };
@endphp

@section('panel')
@if(isset($stats))
<div class="lb-stat-grid">
    <div class="lb-stat lb-stat--info">
        <p class="lb-stat__label">Last 24h</p>
        <p class="lb-stat__value">{{ number_format($stats['total_24h'] ?? 0) }}</p>
        <p class="lb-stat__meta">Total log lines written</p>
    </div>

    <div class="lb-stat {{ ($stats['errors_24h'] ?? 0) > 0 ? 'lb-stat--danger' : '' }}">
        <p class="lb-stat__label">Errors · 24h</p>
        <p class="lb-stat__value">{{ number_format($stats['errors_24h'] ?? 0) }}</p>
        <p class="lb-stat__meta">High-severity emergencies &amp; errors</p>
    </div>

    <div class="lb-stat {{ ($stats['warnings_24h'] ?? 0) > 0 ? 'lb-stat--warn' : '' }}">
        <p class="lb-stat__label">Warnings · 24h</p>
        <p class="lb-stat__value">{{ number_format($stats['warnings_24h'] ?? 0) }}</p>
        <p class="lb-stat__meta">Worth investigating</p>
    </div>

    <div class="lb-stat">
        <p class="lb-stat__label">Top levels · 7d</p>
        <div class="lb-stat__breakdown">
            @forelse(($stats['top_levels_7d'] ?? []) as $it)
                <div class="lb-stat__breakdown-row">
                    <span class="{{ $levelClass($it['level']) }}">{{ strtoupper($it['level']) }}</span>
                    <span class="lb-stat__breakdown-val">{{ number_format($it['count']) }}</span>
                </div>
            @empty
                <div class="lb-stat__breakdown-val">—</div>
            @endforelse
        </div>
    </div>
</div>
@endif

<div class="lb-box lb-box--flat" style="border: 0; border-radius: 0;">
    <form method="GET" class="lb-filter lb-filter--sticky">
        <div class="lb-filter__row">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="lb-input lb-field-sm" aria-label="From date">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="lb-input lb-field-sm" aria-label="To date">
            <select name="level" class="lb-input lb-field-sm" aria-label="Level">
                <option value="">All levels</option>
                @foreach($levels as $lvl)
                    <option value="{{ $lvl }}" @selected(($filters['level'] ?? '') === $lvl)>{{ strtoupper($lvl) }}</option>
                @endforeach
            </select>
            @if(! empty($channels))
                <select name="channel" class="lb-input lb-field-sm" aria-label="Channel">
                    <option value="">All channels</option>
                    @foreach($channels as $ch)
                        <option value="{{ $ch }}" @selected(($filters['channel'] ?? '') === $ch)>{{ $ch }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        <div class="lb-filter__row">
            <input type="text"
                   name="q"
                   value="{{ $filters['q'] ?? '' }}"
                   class="lb-input lb-filter__search"
                   placeholder="Search message  ·  press / to focus"
                   autocomplete="off">
            <button class="lb-btn lb-btn--primary" type="submit">Apply</button>
            <a class="lb-btn lb-btn--ghost" href="{{ cp_route('utilities.logbook.system') }}">Reset</a>
            <a class="lb-btn" href="{{ cp_route('utilities.logbook.system.export', request()->query()) }}" title="Download matching rows as CSV">
                Export CSV
            </a>
            <button type="button"
                    class="lb-live-tail"
                    data-lb-live-tail
                    data-lb-live-tail-json="{{ cp_route('utilities.logbook.system.json') }}"
                    aria-pressed="false"
                    title="Auto-poll for new rows every few seconds">
                <span class="lb-live-tail__dot" aria-hidden="true"></span>
                <span data-lb-live-tail-label>Live tail</span>
            </button>

            <div class="lb-preset" data-lb-preset="system">
                <button type="button" class="lb-btn" data-lb-preset-toggle aria-haspopup="true" aria-expanded="false" title="Saved filter presets">
                    Presets ▾
                </button>
                <div class="lb-preset__menu" role="menu" data-lb-preset-menu>
                    <div data-lb-preset-list></div>
                    <div class="lb-preset__sep"></div>
                    <button type="button" class="lb-preset__item" data-lb-preset-save>
                        <span>Save current filters…</span>
                        <span class="lb-kbd" aria-hidden="true">+</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="lb-box--scroll-x">
    <table class="lb-table">
        <thead>
            <tr>
                <th aria-sort="{{ $ariaSort('created_at') }}">
                    <a class="lb-table__sort" href="{{ $sortLink('created_at') }}" aria-sort="{{ $ariaSort('created_at') }}">Time</a>
                </th>
                <th aria-sort="{{ $ariaSort('level') }}">
                    <a class="lb-table__sort" href="{{ $sortLink('level') }}" aria-sort="{{ $ariaSort('level') }}">Level</a>
                </th>
                <th>Message</th>
                <th aria-sort="{{ $ariaSort('user_id') }}">
                    <a class="lb-table__sort" href="{{ $sortLink('user_id') }}" aria-sort="{{ $ariaSort('user_id') }}">User</a>
                </th>
                <th style="width: 180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
                @php
                    $rowJson = json_encode([
                        'id'         => $row->id,
                        'created_at' => (string) $row->created_at,
                        'level'      => $row->level,
                        'channel'    => $row->channel,
                        'message'    => $row->message,
                        'context'    => $row->context,
                        'request_id' => $row->request_id,
                        'user_id'    => $row->user_id,
                        'ip'         => $row->ip,
                        'method'     => $row->method,
                        'url'        => $row->url,
                        'user_agent' => $row->user_agent,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                @endphp
                <tr>
                    <td class="lb-table__time" title="{{ $row->created_at }}">{{ $humanTime($row->created_at) }}</td>
                    <td>
                        <span class="{{ $levelClass($row->level) }}">
                            <span class="lb-chip__dot" aria-hidden="true"></span>
                            {{ strtoupper($row->level) }}
                        </span>
                    </td>
                    <td class="lb-message-cell">
                        <div class="truncate" title="{{ $row->message }}">{{ $row->message }}</div>
                        @if($row->channel)
                            <div class="lb-table__muted">{{ $row->channel }}</div>
                        @endif
                    </td>
                    <td class="lb-table__muted">{{ $row->user_id ?? '—' }}</td>
                    <td>
                        <div class="lb-row" style="gap: var(--lb-s-1);">
                            @if($row->context)
                                <button type="button"
                                        class="lb-btn lb-btn--sm"
                                        data-lb-modal-title="Context"
                                        data-lb-modal-payload="{{ $b64($row->context) }}"
                                        data-lb-modal-subtitle="{{ $row->message }}"
                                        title="View context payload">Context</button>
                            @endif

                            @if($row->request_id)
                                @php
                                    $req = json_encode([
                                        'request_id' => $row->request_id,
                                        'method'     => $row->method,
                                        'url'        => $row->url,
                                        'ip'         => $row->ip,
                                        'user_agent' => $row->user_agent,
                                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                @endphp
                                <button type="button"
                                        class="lb-btn lb-btn--sm"
                                        data-lb-modal-title="Request"
                                        data-lb-modal-payload="{{ $b64($req) }}"
                                        data-lb-modal-subtitle="{{ $row->method }} {{ $row->url }}"
                                        title="View request details">Request</button>
                            @endif

                            <button type="button"
                                    class="lb-btn lb-btn--sm lb-btn--ghost"
                                    data-lb-modal-title="Row · {{ $row->id }}"
                                    data-lb-modal-payload="{{ $b64($rowJson) }}"
                                    data-lb-modal-subtitle="Copy-friendly JSON"
                                    title="Copy full row as JSON">JSON</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="lb-empty">
                            <div class="lb-empty__icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            </div>
                            <p class="lb-empty__title">No logs match</p>
                            <p class="lb-empty__hint">Try widening the date range or clearing the level / search filters.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($logs, 'links'))
    <div class="lb-pagination">{{ $logs->withQueryString()->links() }}</div>
@endif
@endsection
