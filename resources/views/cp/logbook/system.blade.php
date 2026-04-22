@extends('statamic-logbook::cp.logbook._layout', ['active' => 'system'])

@php
$b64 = fn($v) => base64_encode((string) $v);

$levelColor = fn($l) => match (strtolower((string) $l)) {
'emergency','alert','critical','error' => 'lb-badge lb-badge--error',
'warning' => 'lb-badge lb-badge--warn',
'notice','info' => 'lb-badge lb-badge--info',
'debug' => 'lb-badge lb-badge--debug',
default => 'lb-badge lb-badge--muted'
};
@endphp

<style>
    #main .page-wrapper {
        max-width: 100% !important;
    }

    .footer-pagination nav {
        display: flex;
        flex-direction: column;
        gap: 25px;
        margin-top: 40px;
    }

    .footer-pagination nav> :nth-child(2) {
        gap: 10%;
        align-items: center;
    }
</style>

@section('panel')
@if(isset($stats))
<div class="flex flex-col md:flex-row gap-3 mb-4">
    <div class="card p-3 flex-1">
        <div class="text-xs text-gray-600">Last 24h</div>
        <div class="text-2xl font-semibold mt-1">{{ $stats['total_24h'] ?? 0 }}</div>
        <div class="text-xs text-gray-600 mt-1">Total logs</div>
    </div>

    <div class="card p-3 flex-1">
        <div class="text-xs text-gray-600">Errors/Critical (24h)</div>
        <div class="text-2xl font-semibold mt-1">{{ $stats['errors_24h'] ?? 0 }}</div>
        <div class="text-xs text-gray-600 mt-1">High severity</div>
    </div>

    <div class="card p-3 flex-1">
        <div class="text-xs text-gray-600">Warnings (24h)</div>
        <div class="text-2xl font-semibold mt-1">{{ $stats['warnings_24h'] ?? 0 }}</div>
        <div class="text-xs text-gray-600 mt-1">Investigate</div>
    </div>

    <div class="card p-3 flex-1">
        <div class="text-xs text-gray-600">Top levels (7d)</div>
        <div class="mt-2 space-y-1">
            @forelse(($stats['top_levels_7d'] ?? []) as $it)
            <div class="flex justify-between text-xs">
                <span class="font-mono">{{ $it['level'] }}</span>
                <span class="text-gray-700">{{ $it['count'] }}</span>
            </div>
            @empty
            <div class="text-xs text-gray-600">—</div>
            @endforelse
        </div>
    </div>
</div>
@endif

<form method="GET" class="mb-4">
    <div class="flex flex-col gap-2 w-full">
        <div class="flex flex-row gap-2 items-end flex-1 min-w-[240px]">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input-text lb-field-sm">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input-text lb-field-sm">
        </div>
        <div class="flex flex-col gap-2 items-end flex-1 min-w-[240px]">
            <select name="level" class="input-text lb-field-sm">
                <option value="">All levels</option>
                @foreach($levels as $lvl)
                <option value="{{ $lvl }}" @selected(($filters['level'] ?? '' )===$lvl)>{{ $lvl }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                class="input-text flex-1 min-w-[220px]" placeholder="Search message">
            <button class="btn-primary flex gap-1" type="submit">🔍 Apply</button>
            <a class="btn flex gap-1" href="{{ cp_route('utilities.logbook.system') }}">♻ Reset</a>
            <a class="btn" href="{{ cp_route('utilities.logbook.system.export', request()->query()) }}">⬇ Export CSV</a>
        </div>
    </div>
</form>

<div class="card p-0 overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>Level</th>
                <th>Message</th>
                <th>User</th>
                <th class="w-[140px]">Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
            <tr>
                <td class="text-xs whitespace-nowrap">{{ $row->created_at }}</td>

                <td>
                    <span class="{{ $levelColor($row->level) }}">
                        {{ strtoupper($row->level) }}
                    </span>
                </td>

                <td class="lb-message-cell">
                    <div class="truncate" title="{{ $row->message }}">{{ $row->message }}</div>
                </td>

                <td class="text-xs">{{ $row->user_id ?? '—' }}</td>

                <td>
                    <div class="flex gap-1">
                        @if($row->context)
                        <button
                            type="button"
                            class="btn"
                            onclick="__logbookOpenModal('Context', '{{ $b64($row->context) }}', '{{ addslashes($row->message) }}')"
                            title="View context">🧾</button>
                        @endif

                        @if($row->request_id)
                        @php
                        $req = json_encode([
                        'request_id' => $row->request_id,
                        'method' => $row->method,
                        'url' => $row->url,
                        'ip' => $row->ip,
                        'user_agent' => $row->user_agent,
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        @endphp
                        <button
                            type="button"
                            class="btn"
                            onclick="__logbookOpenModal('Request', '{{ $b64($req) }}', '{{ addslashes($row->method.' '.$row->url) }}')"
                            title="View request">🌐</button>
                        @endif

                        @if(!$row->context && !$row->request_id)
                        <span class="text-xs text-gray-500">—</span>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="p-4 text-gray-600">No logs found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($logs, 'links'))
<div class="mt-4 footer-pagination">{{ $logs->withQueryString()->links() }}</div>
@endif
@endsection