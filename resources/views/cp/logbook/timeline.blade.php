@extends('statamic-logbook::cp.logbook._layout', ['active' => 'timeline'])

@php
    $b64 = fn($v) => base64_encode((string) $v);
    $humanTime = function ($carbon) {
        try {
            return $carbon->diffForHumans();
        } catch (\Throwable $e) {
            return (string) $carbon;
        }
    };
    $dayLabel = function (string $ymd) {
        try {
            $dt = \Carbon\Carbon::parse($ymd);
            if ($dt->isToday())     return 'Today';
            if ($dt->isYesterday()) return 'Yesterday';
            return $dt->isoFormat('dddd · MMM D, YYYY');
        } catch (\Throwable $e) { return $ymd; }
    };
    $entryVariant = fn ($it) => match ($it['severity'] ?? 'info') {
        'error' => 'error',
        'warn'  => 'warn',
        'audit' => 'audit',
        default => 'system',
    };

    $sevSelected  = (array) ($sev ?? []);
    $typesChecked = (array) ($types ?? ['system', 'audit']);
@endphp

@section('panel')
<div class="lb-box lb-box--flat" style="border: 0; border-radius: 0;">
    <form method="GET" class="lb-filter lb-filter--sticky" data-lb-filter-form>
        <div class="lb-filter__row">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="lb-input lb-field-sm" aria-label="From date">
            <input type="date" name="to"   value="{{ $filters['to'] ?? '' }}"   class="lb-input lb-field-sm" aria-label="To date">

            <label class="lb-pill {{ in_array('system', $typesChecked, true) ? 'lb-pill--active' : '' }}">
                <input type="checkbox" name="types[]" value="system" {{ in_array('system', $typesChecked, true) ? 'checked' : '' }} class="lb-sr-only">
                System
            </label>
            <label class="lb-pill {{ in_array('audit', $typesChecked, true) ? 'lb-pill--active' : '' }}">
                <input type="checkbox" name="types[]" value="audit" {{ in_array('audit', $typesChecked, true) ? 'checked' : '' }} class="lb-sr-only">
                Audit
            </label>

            <label class="lb-pill {{ in_array('error', $sevSelected, true) ? 'lb-pill--active' : '' }}">
                <input type="checkbox" name="sev[]" value="error" {{ in_array('error', $sevSelected, true) ? 'checked' : '' }} class="lb-sr-only">
                Errors
            </label>
            <label class="lb-pill {{ in_array('warn', $sevSelected, true) ? 'lb-pill--active' : '' }}">
                <input type="checkbox" name="sev[]" value="warn" {{ in_array('warn', $sevSelected, true) ? 'checked' : '' }} class="lb-sr-only">
                Warnings
            </label>
            <label class="lb-pill {{ in_array('info', $sevSelected, true) ? 'lb-pill--active' : '' }}">
                <input type="checkbox" name="sev[]" value="info" {{ in_array('info', $sevSelected, true) ? 'checked' : '' }} class="lb-sr-only">
                Info
            </label>
        </div>

        <div class="lb-filter__row">
            <input type="text"
                   name="q"
                   value="{{ $filters['q'] ?? '' }}"
                   class="lb-input lb-filter__search"
                   placeholder="Search message, subject title, handle, or action  ·  press / to focus"
                   autocomplete="off">
            <button class="lb-btn lb-btn--primary" type="submit">Apply</button>
            <a class="lb-btn lb-btn--ghost" href="{{ cp_route('utilities.logbook.timeline') }}">Reset</a>
        </div>

        <div class="lb-filter__row" style="justify-content: space-between;">
            <p class="lb-table__muted">
                Showing <strong>{{ number_format($itemCount) }}</strong>
                event{{ $itemCount === 1 ? '' : 's' }} across
                {{ count($grouped) }} day{{ count($grouped) === 1 ? '' : 's' }}
                @if($itemCount >= $limit) (capped at {{ $limit }}) @endif.
            </p>
        </div>
    </form>
</div>

@if($itemCount === 0)
    <div class="lb-empty">
        <div class="lb-empty__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        </div>
        <p class="lb-empty__title">No events in this window</p>
        <p class="lb-empty__hint">Try widening the date range or toggling the type / severity filters.</p>
    </div>
@else
    <div class="lb-panel-body">
        <div class="lb-timeline" role="list">
            @foreach($grouped as $day => $items)
                <div class="lb-timeline__day">{{ $dayLabel($day) }} · {{ count($items) }}</div>
                @foreach($items as $it)
                    @php $variant = $entryVariant($it); @endphp
                    <div class="lb-timeline__entry lb-timeline__entry--{{ $variant }}" role="listitem">
                        <div class="lb-timeline__time" title="{{ $it['at']->toDateTimeString() }}">
                            {{ $it['at']->format('H:i') }}
                        </div>
                        <div class="lb-timeline__body">
                            <p class="lb-timeline__msg">
                                @if($it['type'] === 'system')
                                    <span class="lb-chip lb-chip--{{ $variant }}" style="margin-right: var(--lb-s-2);">
                                        <span class="lb-chip__dot" aria-hidden="true"></span>
                                        {{ strtoupper($it['label']) }}
                                    </span>
                                @else
                                    {{-- Audit rows carry the presenter-derived chip variant + humanised label
                                         ("User updated", "Entry created", …). The raw event string
                                         (e.g. statamic.user.updated) is kept in `actionRaw` and
                                         surfaced as a tooltip for operators who prefer the machine name. --}}
                                    <span class="lb-chip lb-chip--{{ $it['variant'] ?? 'update' }}"
                                          style="margin-right: var(--lb-s-2);"
                                          title="{{ $it['actionRaw'] ?? '' }}">
                                        <span class="lb-chip__dot" aria-hidden="true"></span>
                                        {{ $it['label'] }}
                                    </span>
                                @endif
                                {{ $it['message'] }}
                            </p>
                            <div class="lb-timeline__sub">
                                <span class="lb-feed__sub--mono">{{ $it['meta'] }}</span>
                                @if(! empty($it['user']))
                                    <span>·</span>
                                    <span>{{ $it['user'] }}</span>
                                @endif
                                <span>·</span>
                                <span title="{{ $it['at']->toDateTimeString() }}">{{ $humanTime($it['at']) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
@endif
@endsection
