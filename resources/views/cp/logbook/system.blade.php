@extends('statamic-logbook::cp.logbook._layout', ['active' => 'system'])

@section('panel')
<form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
    <div>
        <label class="block text-xs mb-1">From</label>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input-text">
    </div>
    <div>
        <label class="block text-xs mb-1">To</label>
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input-text">
    </div>

    <div>
        <label class="block text-xs mb-1">Level</label>
        <select name="level" class="input-text">
            <option value="">All</option>
            @foreach($levels as $lvl)
                <option value="{{ $lvl }}" @selected(($filters['level'] ?? '') === $lvl)>{{ $lvl }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs mb-1">Channel</label>
        <select name="channel" class="input-text">
            <option value="">All</option>
            @foreach($channels as $ch)
                <option value="{{ $ch }}" @selected(($filters['channel'] ?? '') === $ch)>{{ $ch }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex-1 min-w-[220px]">
        <label class="block text-xs mb-1">Search</label>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="input-text" placeholder="message contains...">
    </div>

    <div class="flex gap-2">
        <button class="btn-primary" type="submit">Apply</button>
        <a class="btn" href="{{ cp_route('utilities.logbook.system') }}">Reset</a>
    </div>
</form>

<div class="card p-0 overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th class="w-40">Time</th>
                <th class="w-24">Level</th>
                <th>Message</th>
                <th class="w-32">User</th>
                <th class="w-56">Request</th>
            </tr>
        </thead>
        <tbody>
        @forelse($logs as $row)
            <tr>
                <td class="text-xs">{{ $row->created_at }}</td>
                <td class="text-xs font-mono">{{ $row->level }}</td>
                <td class="text-sm">
                    <div class="font-medium">{{ $row->message }}</div>
                    @if($row->context)
                        <details class="mt-1">
                            <summary class="text-xs text-gray-600 cursor-pointer">context</summary>
                            <pre class="text-xs whitespace-pre-wrap">{{ $row->context }}</pre>
                        </details>
                    @endif
                </td>
                <td class="text-xs font-mono">{{ $row->user_id ?: '—' }}</td>
                <td class="text-xs font-mono">
                    <div>{{ $row->request_id ?: '—' }}</div>
                    @if($row->method || $row->url)
                        <div class="text-gray-600">{{ $row->method }} {{ $row->url }}</div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="p-4 text-sm text-gray-600">No logs found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>
@endsection
