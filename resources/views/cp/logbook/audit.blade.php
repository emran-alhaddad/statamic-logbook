@extends('statamic-logbook::cp.logbook._layout', ['active' => 'audit'])

@php
$b64 = fn($v) => base64_encode((string) $v);

$actionColor = fn($a) => match (true) {
str_contains($a,'created') => 'bg-green-100 text-green-700',
str_contains($a,'updated') || str_contains($a,'saved') => 'bg-blue-100 text-blue-700',
str_contains($a,'deleted') => 'bg-red-100 text-red-700',
default => 'bg-gray-200 text-gray-700'
};
@endphp

@section('panel')
<form method="GET" class="mb-4">
    <div class="flex flex-wrap gap-2 items-end">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input-text w-40">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input-text w-40">

        <select name="action" class="input-text w-56">
            <option value="">All actions</option>
            @foreach($actions as $a)
            <option value="{{ $a }}" @selected(($filters['action'] ?? '' )===$a)>{{ $a }}</option>
            @endforeach
        </select>

        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
            class="input-text flex-1 min-w-[200px]" placeholder="Search subject">

        <button class="btn-primary flex gap-1">🔍 Apply</button>
        <a class="btn flex gap-1" href="{{ cp_route('utilities.logbook.audit') }}">♻ Reset</a>
        <a class="btn" href="{{ cp_route('utilities.logbook.audit.export', request()->query()) }}">
            ⬇ Export CSV
        </a>

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
                <th>Changes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
            <tr>
                <td class="text-xs">{{ $row->created_at }}</td>

                <td>
                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $actionColor($row->action) }}">
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
                    @endphp
                    <button class="btn" onclick="__logbookOpenModal('Audit Changes', '{{ $b64($payload) }}')">🧠 View</button>
                    @else
                    —
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

<div class="mt-4">{{ $logs->links() }}</div>
@endsection