{{--
    Logbook — Overview card widget
    --------------------------------------------------------------
    Premium-minimal redesign (v2). Adds inline SVG sparklines +
    period-over-period deltas for the three primary KPIs. Data is
    consumed from LogbookDashboardData::summary():
        systemTotal24h / systemErrors24h / auditTotal24h
        systemSpark24h / errorSpark24h / auditSpark24h (24 ints)
        systemDelta / errorDelta / auditDelta
            (shape: ['value' => int, 'pct' => float|null,
                     'direction' => 'up'|'down'|'flat'])

    Styling comes from the addon-shipped stylesheet
    (resources/dist/statamic-logbook.css), auto-registered via
    $stylesheets on LogbookServiceProvider. We deliberately avoid
    CP Tailwind utilities that get purged by the host CP's JIT
    build.
--}}
@php
    /**
     * Render a small inline sparkline as SVG.
     *
     * @param list<int> $series Oldest → newest values (fixed count)
     * @param string $variant 'accent' | 'danger' | 'warn' | 'ok'
     */
    $renderSpark = function (array $series, string $variant = 'accent') {
        $n = count($series);
        if ($n < 2) return '';

        $w = 100;   // viewBox width
        $h = 32;    // viewBox height
        $pad = 2;   // top/bottom padding so stroke isn't clipped

        $max = max($series);
        $min = min($series);
        $range = max(1, $max - $min);

        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $x = $n === 1 ? 0 : ($i / ($n - 1)) * $w;
            $y = $h - $pad - (($series[$i] - $min) / $range) * ($h - ($pad * 2));
            $pts[] = round($x, 2).','.round($y, 2);
        }

        $line = 'M '.implode(' L ', $pts);
        $area = $line.' L '.round($w, 2).','.round($h, 2).' L 0,'.round($h, 2).' Z';

        $pathClass = $variant === 'accent' ? 'lb-spark__path' : 'lb-spark__path lb-spark__path--'.$variant;
        $areaClass = $variant === 'accent' ? 'lb-spark__area' : 'lb-spark__area lb-spark__area--'.$variant;

        // Last point dot
        [$lx, $ly] = explode(',', end($pts));

        return
            '<svg class="lb-spark lb-card__spark" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none" aria-hidden="true">'.
                '<path class="'.$areaClass.'" d="'.$area.'"/>'.
                '<path class="'.$pathClass.'" d="'.$line.'"/>'.
                '<circle class="lb-spark__dot" cx="'.$lx.'" cy="'.$ly.'" r="2"/>'.
            '</svg>';
    };

    $renderDelta = function (array $delta) {
        $dir = $delta['direction'] ?? 'flat';
        $pct = $delta['pct'] ?? null;
        $val = $delta['value'] ?? 0;

        $arrow = $dir === 'up' ? '↑' : ($dir === 'down' ? '↓' : '—');
        $sign  = $val > 0 ? '+' : '';
        $txt   = $pct === null
            ? $sign.$val                         // prior period was 0 and current isn't
            : ($val === 0 ? '0%' : $sign.$pct.'%');

        return '<span class="lb-card__delta lb-card__delta--'.$dir.'" title="vs previous 24h">'.
                    $arrow.' '.e($txt).
                '</span>';
    };
@endphp

<div class="lb-stack">
    <section>
        <div class="lb-header">
            <div>
                <p class="lb-header__kicker">Logbook</p>
                <h2 class="lb-header__title">Health overview</h2>
                <p class="lb-header__meta">Rolling 24h snapshot · compared to prior 24h</p>
            </div>
            <a href="{{ cp_route('utilities.logbook.system') }}" class="lb-header__link">
                Open utility <span aria-hidden="true">→</span>
            </a>
        </div>

        <div class="lb-cards">
            {{-- System · 24h --}}
            <div class="lb-card">
                <p class="lb-card__label">System · 24h</p>
                <p class="lb-card__value">{{ number_format($systemTotal24h) }}</p>
                <p class="lb-card__meta">
                    {!! $renderDelta($systemDelta ?? ['value' => 0, 'pct' => 0.0, 'direction' => 'flat']) !!}
                    <span>vs prior 24h</span>
                </p>
                {!! $renderSpark($systemSpark24h ?? [], 'accent') !!}
            </div>

            {{-- Errors · 24h (now with "last error ago" chip — F5) --}}
            <div class="lb-card {{ $systemErrors24h > 0 ? 'lb-card--danger' : '' }}">
                @if($systemErrors24h > 0)
                    <span class="lb-card__badge lb-card__badge--danger">Attention</span>
                @else
                    <span class="lb-card__badge lb-card__badge--ok">OK</span>
                @endif
                <p class="lb-card__label {{ $systemErrors24h > 0 ? 'lb-card__label--danger' : '' }}">Errors · 24h</p>
                <p class="lb-card__value">{{ number_format($systemErrors24h) }}</p>
                <p class="lb-card__meta {{ $systemErrors24h > 0 ? 'lb-card__meta--danger' : '' }}">
                    {!! $renderDelta($errorDelta ?? ['value' => 0, 'pct' => 0.0, 'direction' => 'flat']) !!}
                    @if($systemTotal24h > 0)
                        <span>· <strong>{{ $errorRatio }}%</strong> of volume</span>
                    @else
                        <span>· no volume in window</span>
                    @endif
                </p>
                @if(! empty($lastError))
                    <p class="lb-card__chip" title="Most recent error: {{ $lastError['at']->toDateTimeString() }}">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        Last error {{ $lastError['ago'] }} ago
                    </p>
                @else
                    <p class="lb-card__chip lb-card__chip--ok">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        No errors in 72h
                    </p>
                @endif
                {!! $renderSpark($errorSpark24h ?? [], 'danger') !!}
            </div>

            {{-- Audit · 24h --}}
            <div class="lb-card">
                <p class="lb-card__label">Audit · 24h</p>
                <p class="lb-card__value">{{ number_format($auditTotal24h) }}</p>
                <p class="lb-card__meta">
                    {!! $renderDelta($auditDelta ?? ['value' => 0, 'pct' => 0.0, 'direction' => 'flat']) !!}
                    <span>vs prior 24h</span>
                </p>
                {!! $renderSpark($auditSpark24h ?? [], 'warn') !!}
            </div>

            {{-- Peak hour · 24h (F5) --}}
            <div class="lb-card">
                <p class="lb-card__label">Busiest hour · 24h</p>
                @if(! empty($peakHour24h) && $peakHour24h['count'] > 0)
                    <p class="lb-card__value">{{ number_format($peakHour24h['count']) }}</p>
                    <p class="lb-card__meta">
                        <span>at <strong>{{ $peakHour24h['label'] }}</strong></span>
                        <span>· {{ $errorRatio }}% error ratio window-wide</span>
                    </p>
                @else
                    <p class="lb-card__value">—</p>
                    <p class="lb-card__meta"><span>No system volume in the current window.</span></p>
                @endif
                {!! $renderSpark($systemSpark24h ?? [], 'ok') !!}
            </div>
        </div>
    </section>

    {{-- Top error signatures — groups raw errors by shape so 500 rows
         of the same exception collapse into one actionable row. (F5) --}}
    @if(! empty($errorFingerprints))
        <section>
            <div class="lb-header lb-header--tight">
                <div>
                    <p class="lb-panel__label">Top error signatures · 24h</p>
                    <p class="lb-header__meta">Grouped by normalised message shape — same error, different IDs cluster together.</p>
                </div>
                <a href="{{ cp_route('utilities.logbook.system', ['level' => 'error']) }}" class="lb-header__link">
                    View errors <span aria-hidden="true">→</span>
                </a>
            </div>
            <div class="lb-signatures">
                @foreach($errorFingerprints as $sig)
                    <a class="lb-signature"
                       href="{{ cp_route('utilities.logbook.system', ['level' => $sig['level'], 'q' => \Illuminate\Support\Str::limit($sig['example'], 48, '')]) }}"
                       title="{{ $sig['example'] }}">
                        <span class="lb-signature__count">{{ number_format($sig['count']) }}</span>
                        <span class="lb-signature__body">
                            <span class="lb-signature__sig">{{ $sig['signature'] }}</span>
                            <span class="lb-signature__meta">
                                <span class="lb-chip lb-chip--error" style="padding: 0 6px;">{{ strtoupper($sig['level']) }}</span>
                                <span>Last {{ $sig['last_at']->diffForHumans() }}</span>
                            </span>
                        </span>
                        <span class="lb-signature__cta" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="lb-panel-grid">
        @if(! empty($userActivity))
            <div class="lb-panel">
                <p class="lb-panel__label">Team activity · 7d</p>
                <div class="lb-user-list">
                    @foreach($userActivity as $u)
                        @php
                            $email = $u['email'] ?: ('User '.$u['user_id']);
                            $initial = mb_strtoupper(mb_substr($email, 0, 1));
                        @endphp
                        <div class="lb-user-row">
                            <div class="lb-avatar" aria-hidden="true">{{ $initial }}</div>
                            <div class="lb-user-meta">
                                <p class="lb-user-email">{{ $email }}</p>
                                <p class="lb-user-sub">
                                    <span>Last activity {{ $u['last_at']->diffForHumans() }}</span>
                                    <span>· <strong>{{ $u['actions'] }} actions</strong></span>
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="lb-panel">
                <p class="lb-panel__label">Team activity · 7d</p>
                <div class="lb-empty">
                    <div class="lb-empty__icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c1.5-3.5 4-5 7-5s5.5 1.5 7 5"/></svg>
                    </div>
                    <p class="lb-empty__title">No team audit activity</p>
                    <p class="lb-empty__hint">Activity appears here once users perform audited actions.</p>
                </div>
            </div>
        @endif

        <div class="lb-panel">
            <p class="lb-panel__label">Top audit action · 7d</p>
            @if($topAction7d)
                <div class="lb-action-row">
                    <div>
                        <p class="lb-action-row__name" title="{{ $topAction7d->action }}">{{ \EmranAlhaddad\StatamicLogbook\Support\AuditActionPresenter::label($topAction7d->action) }}</p>
                        <p class="lb-action-row__count">{{ number_format($topAction7d->c) }} occurrences</p>
                    </div>
                    <a href="{{ cp_route('utilities.logbook.audit') }}" class="lb-action-row__link">
                        Audit log <span aria-hidden="true">→</span>
                    </a>
                </div>
            @else
                <div class="lb-empty">
                    <div class="lb-empty__icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>
                    </div>
                    <p class="lb-empty__title">No audit events</p>
                    <p class="lb-empty__hint">Action breakdowns appear here once the audit log fills up.</p>
                </div>
            @endif
        </div>
    </section>
</div>
