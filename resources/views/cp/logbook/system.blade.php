@extends('statamic-logbook::cp.logbook._layout', ['active' => 'system'])

@php
$b64 = fn($v) => base64_encode((string) $v);

$levelColor = fn($l) => match ($l) {
'emergency','alert','critical','error' => 'bg-red-100 text-red-700',
'warning' => 'bg-yellow-100 text-yellow-700',
'notice','info' => 'bg-blue-100 text-blue-700',
'debug' => 'bg-gray-200 text-gray-700',
default => 'bg-gray-200 text-gray-700'
};
@endphp

@section('panel')
<form method="GET" class="mb-4">
    <div class="flex flex-wrap gap-2 items-end">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input-text w-40">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input-text w-40">

        <select name="level" class="input-text w-40">
            <option value="">All levels</option>
            @foreach($levels as $lvl)
            <option value="{{ $lvl }}" @selected(($filters['level'] ?? '' )===$lvl)>{{ $lvl }}</option>
            @endforeach
        </select>

        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
            class="input-text flex-1 min-w-[200px]" placeholder="Search message">

        <button class="btn-primary flex gap-1">🔍 Apply</button>
        <a class="btn flex gap-1" href="{{ cp_route('utilities.logbook.system') }}">♻ Reset</a>
        <a class="btn" href="{{ cp_route('utilities.logbook.system.export', request()->query()) }}">
            ⬇ Export CSV
        </a>
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
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
            <tr>
                <td class="text-xs">{{ $row->created_at }}</td>

                <td>
                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $levelColor($row->level) }}">
                        {{ $row->level }}
                    </span>
                </td>

                <td>{{ $row->message }}</td>
                <td class="text-xs">{{ $row->user_id ?? '—' }}</td>

                <td>
                    <div class="flex gap-1">
                        @if($row->context)
                        <button class="btn" onclick="__logbookOpenModal('Context', '{{ $b64($row->context) }}')">🧾</button>
                        @endif

                        @if($row->request_id)
                        @php
                        $req = json_encode([
                        'request_id' => $row->request_id,
                        'method' => $row->method,
                        'url' => $row->url,
                        'ip' => $row->ip,
                        ], JSON_PRETTY_PRINT);
                        @endphp
                        <button class="btn" onclick="__logbookOpenModal('Request', '{{ $b64($req) }}')">🌐</button>
                        @endif

                        @if(!$row->context && !$row->request_id)
                        —
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

<div class="mt-4">{{ $logs->links() }}</div>
@endsection