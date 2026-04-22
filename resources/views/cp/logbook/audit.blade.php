@extends('statamic-logbook::cp.logbook._layout', ['active' => 'audit'])

@php
    $b64 = fn($v) => base64_encode((string) $v);

    $actionClass = fn($a) => match (true) {
        str_contains((string) $a, 'deleted')                                        => 'lb-chip lb-chip--delete',
        str_contains((string) $a, 'created')                                        => 'lb-chip lb-chip--create',
        str_contains((string) $a, 'updated') || str_contains((string) $a, 'saved') => 'lb-chip lb-chip--update',
        default                                                                     => 'lb-chip lb-chip--muted',
    };

    $sort = $sort ?? 'id';
    $dir  = $dir  ?? 'desc';
    $sortLink = function (string $col) use ($sort, $dir, $filters) {
        $nextDir = ($sort === $col && $dir === 'desc') ? 'asc' : 'desc';
        $query   = array_filter(array_merge($filters ?? [], ['sort' => $col, 'dir' => $nextDir]));
        return cp_route('utilities.logbook.audit').'?'.http_build_query($query);
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
        <p class="lb-stat__meta">Total audit actions</p>
    </div>

    <div class="lb-stat">
        <p class="lb-stat__label">Top actions · 7d</p>
        <div class="lb-stat__breakdown">
            @forelse(($stats['top_actions_7d'] ?? []) as $it)
                <div class="lb-stat__breakdown-row">
                    <span class="lb-stat__breakdown-key" title="{{ $it['action'] }}">{{ $it['action'] }}</span>
                    <span class="lb-stat__breakdown-val">{{ number_format($it['count']) }}</span>
                </div>
            @empty
                <div class="lb-stat__breakdown-val">—</div>
            @endforelse
        </div>
    </div>

    <div class="lb-stat">
        <p class="lb-stat__label">Top users · 7d</p>
        <div class="lb-stat__breakdown">
            @forelse(($stats['top_users_7d'] ?? []) as $it)
                <div class="lb-stat__breakdown-row">
                    <span class="truncate" title="{{ $it['user'] }}">{{ $it['user'] }}</span>
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
            <select name="action" class="lb-input lb-field-md" aria-label="Action">
                <option value="">All actions</option>
                @foreach($actions as $a)
                    <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
                @endforeach
            </select>
            @if(! empty($subjects))
                <select name="subject_type" class="lb-input lb-field-md" aria-label="Subject type">
                    <option value="">All subjects</option>
                    @foreach($subjects as $s)
                        <option value="{{ $s }}" @selected(($filters['subject_type'] ?? '') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        <div class="lb-filter__row">
            <input type="text"
                   name="q"
                   value="{{ $filters['q'] ?? '' }}"
                   class="lb-input lb-filter__search"
                   placeholder="Search subject title or handle  ·  press / to focus"
                   autocomplete="off">
            <button class="lb-btn lb-btn--primary" type="submit">Apply</button>
            <a class="lb-btn lb-btn--ghost" href="{{ cp_route('utilities.logbook.audit') }}">Reset</a>
            <a class="lb-btn" href="{{ cp_route('utilities.logbook.audit.export', request()->query()) }}" title="Download matching rows as CSV">
                Export CSV
            </a>
            <button type="button"
                    class="lb-live-tail"
                    data-lb-live-tail
                    data-lb-live-tail-json="{{ cp_route('utilities.logbook.audit.json') }}"
                    aria-pressed="false"
                    title="Auto-poll for new rows every few seconds">
                <span class="lb-live-tail__dot" aria-hidden="true"></span>
                <span data-lb-live-tail-label>Live tail</span>
            </button>

            <div class="lb-preset" data-lb-preset="audit">
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
                <th aria-sort="{{ $ariaSort('action') }}">
                    <a class="lb-table__sort" href="{{ $sortLink('action') }}" aria-sort="{{ $ariaSort('action') }}">Action</a>
                </th>
                <th aria-sort="{{ $ariaSort('subject_type') }}">
                    <a class="lb-table__sort" href="{{ $sortLink('subject_type') }}" aria-sort="{{ $ariaSort('subject_type') }}">Subject</a>
                </th>
                <th aria-sort="{{ $ariaSort('user_email') }}">
                    <a class="lb-table__sort" href="{{ $sortLink('user_email') }}" aria-sort="{{ $ariaSort('user_email') }}">User</a>
                </th>
                <th style="width: 160px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
                @php
                    $rowJson = json_encode([
                        'id'              => $row->id,
                        'created_at'      => (string) $row->created_at,
                        'action'          => $row->action,
                        'user_id'         => $row->user_id,
                        'user_email'      => $row->user_email,
                        'subject_type'    => $row->subject_type,
                        'subject_id'      => $row->subject_id,
                        'subject_handle'  => $row->subject_handle,
                        'subject_title'   => $row->subject_title,
                        'changes'         => $row->changes ? json_decode($row->changes, true) : null,
                        'meta'            => isset($row->meta) && $row->meta ? json_decode($row->meta, true) : null,
                        'ip'              => $row->ip,
                        'user_agent'      => $row->user_agent,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                @endphp
                <tr>
                    <td class="lb-table__time" title="{{ $row->created_at }}">{{ $humanTime($row->created_at) }}</td>
                    <td>
                        <span class="{{ $actionClass($row->action) }}">
                            <span class="lb-chip__dot" aria-hidden="true"></span>
                            {{ $row->action }}
                        </span>
                    </td>
                    <td>
                        <div style="font-weight: 500;">{{ $row->subject_title ?? $row->subject_handle ?? '—' }}</div>
                        <div class="lb-table__muted">{{ $row->subject_type }} · {{ $row->subject_id }}</div>
                    </td>
                    <td class="lb-table__muted">{{ $row->user_email ?? $row->user_id ?? '—' }}</td>
                    <td>
                        <div class="lb-row" style="gap: var(--lb-s-1);">
                            @if($row->changes)
                                @php
                                    $payload  = json_encode(json_decode($row->changes, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                    $subtitle = ($row->action ?? '').' · '.($row->subject_type ?? '');
                                @endphp
                                <button type="button"
                                        class="lb-btn lb-btn--sm"
                                        data-lb-modal-title="Audit Changes"
                                        data-lb-modal-payload="{{ $b64($payload) }}"
                                        data-lb-modal-subtitle="{{ $subtitle }}"
                                        title="View diff payload">View</button>
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
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                            </div>
                            <p class="lb-empty__title">No audit logs match</p>
                            <p class="lb-empty__hint">Try widening the date range or clearing the action / subject filters.</p>
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
