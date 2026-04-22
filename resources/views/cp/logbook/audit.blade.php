@extends('statamic-logbook::cp.logbook._layout', ['active' => 'audit'])

@php
$b64 = fn($v) => base64_encode((string) $v);

$actionColor = fn($a) => match (true) {
str_contains((string)$a, 'deleted') => 'lb-badge lb-badge--delete',
str_contains((string)$a, 'created') => 'lb-badge lb-badge--create',
str_contains((string)$a, 'updated') || str_contains((string)$a, 'saved') => 'lb-badge lb-badge--update',
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

    .footer-pagination nav > :nth-child(2)  {
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
        <div class="text-xs text-gray-600 mt-1">Total audit actions</div>
    </div>

    <div class="card p-3 flex-1">
        <div class="text-xs text-gray-600">Top actions (7d)</div>
        <div class="mt-2 space-y-1">
            @forelse(($stats['top_actions_7d'] ?? []) as $it)
            <div class="flex justify-between text-xs">
                <span class="font-mono truncate" title="{{ $it['action'] }}">{{ $it['action'] }}</span>
                <span class="text-gray-700">{{ $it['count'] }}</span>
            </div>
            @empty
            <div class="text-xs text-gray-600">—</div>
            @endforelse
        </div>
    </div>

    <div class="card p-3">
        <div class="text-xs text-gray-600">Top users (7d)</div>
        <div class="mt-2 space-y-1">
            @forelse(($stats['top_users_7d'] ?? []) as $it)
            <div class="flex justify-between text-xs">
                <span class="truncate" title="{{ $it['user'] }}">{{ $it['user'] }}</span>
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
    <div class="flex flex-col gap-2  w-full">
        <div class="flex flex-row gap-2 items-end flex-1 min-w-[240px]">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input-text lb-field-sm">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input-text lb-field-sm">
        </div>
        <div class="flex flex-col gap-2 items-end flex-1 min-w-[240px]">
            <select name="action" class="input-text w-56">
                <option value="">All actions</option>
                @foreach($actions as $a)
                <option value="{{ $a }}" @selected(($filters['action'] ?? '' )===$a)>{{ $a }}</option>
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
                <th>Action</th>
                <th>Subject</th>
                <th>User</th>
                <th class="w-[140px]">Changes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
            <tr>
                <td class="text-xs whitespace-nowrap">{{ $row->created_at }}</td>

                <td>
                    <span class="{{ $actionColor($row->action) }}">
                        {{ $row->action }}
                    </span>
                </td>

                <td>
                    <div class="font-medium">{{ $row->subject_title ?? $row->subject_handle }}</div>
                    <div class="text-xs text-gray-600">{{ $row->subject_type }} · {{ $row->subject_id }}</div>
                </td>

                <td class="text-xs">{{ $row->user_email ?? $row->user_id ?? '—' }}</td>

                <td>
                    @if($row->changes)
                    @php
                    $payload = json_encode(json_decode($row->changes, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $subtitle = ($row->action ?? '').' • '.($row->subject_type ?? '');
                    @endphp
                    <button
                        type="button"
                        class="btn"
                        onclick="__logbookOpenModal('Audit Changes', '{{ $b64($payload) }}', '{{ addslashes($subtitle) }}')"
                        title="View changes">🧠 View</button>
                    @else
                    <span class="text-xs text-gray-500">—</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="p-4 text-gray-600">No audit logs found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($logs, 'links'))
<div class="mt-4 footer-pagination">{{ $logs->withQueryString()->links() }}</div>
@endif
@endsection