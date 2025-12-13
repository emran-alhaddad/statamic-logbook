@extends('statamic-logbook::cp.logbook._layout', ['active' => 'audit'])

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
        <label class="block text-xs mb-1">Action</label>
        <select name="action" class="input-text">
            <option value="">All</option>
            @foreach($actions as $a)
                <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>
                    {{ $a }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs mb-1">Subject</label>
        <select name="subject_type" class="input-text">
            <option value="">All</option>
            @foreach($subjects as $s)
                <option value="{{ $s }}" @selected(($filters['subject_type'] ?? '') === $s)>
                    {{ $s }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="flex-1 min-w-[220px]">
        <label class="block text-xs mb-1">Search</label>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="input-text" placeholder="title / handle contains...">
    </div>

    <div class="flex gap-2">
        <button class="btn-primary" type="submit">Apply</button>
        <a class="btn" href="{{ cp_route('utilities.logbook.audit') }}">Reset</a>
    </div>
</form>

<div class="card p-0 overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th class="w-40">Time</th>
                <th class="w-48">Action</th>
                <th>Subject</th>
                <th class="w-32">User</th>
                <th class="w-72">Changes</th>
            </tr>
        </thead>
        <tbody>
        @forelse($logs as $row)
            <tr>
                <td class="text-xs">{{ $row->created_at }}</td>
                <td class="text-xs font-mono">{{ $row->action }}</td>

                <td class="text-sm">
                    <div class="font-medium">
                        {{ $row->subject_title ?? $row->subject_handle ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-600">
                        {{ $row->subject_type }} · {{ $row->subject_id }}
                    </div>
                </td>

                <td class="text-xs font-mono">
                    {{ $row->user_email ?? $row->user_id ?? '—' }}
                </td>

                <td class="text-xs">
                    @if($row->changes)
                        @php($changes = json_decode($row->changes, true))
                        <details>
                            <summary class="cursor-pointer text-gray-600">view</summary>
                            <div class="mt-1 space-y-1">
                                @foreach($changes as $field => $diff)
                                    <div>
                                        <strong>{{ $field }}</strong>:
                                        <span class="text-red-500">{{ $diff['from'] ?? '—' }}</span>
                                        →
                                        <span class="text-green-600">{{ $diff['to'] ?? '—' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="p-4 text-sm text-gray-600">No audit logs found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>
@endsection
