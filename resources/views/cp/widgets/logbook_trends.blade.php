{{--
    Logbook — Volume trends (stacked bars) widget
    --------------------------------------------------------------
    See logbook_cards.blade.php header for the rationale behind
    the lb-* class namespace. The chart is CSS-only (flex columns
    with `flex: <count> 1 0` to proportion stacked segments) —
    no chart library is pulled in.
--}}
@php
    $n = max(1, count($bars));
    $totals = [];
    foreach ($bars as $b) {
        $info = $b['system_info'] ?? max(0, ($b['system'] ?? 0) - ($b['errors'] ?? 0));
        $totals[] = (int) ($b['errors'] ?? 0) + (int) $info + (int) ($b['audit'] ?? 0);
    }
    $maxTotal = max($totals) ?: 1;
@endphp

<div>
    <p class="lb-header__kicker">Logbook</p>
    <h2 class="lb-header__title">Volume by day</h2>
    <p class="lb-header__meta">Stacked column height = day total · colours = share by type · numbers below</p>

    <div class="lb-chart" style="margin-top: 1rem;">
        <div class="lb-legend">
            <span class="lb-legend__chip lb-legend__chip--errors">Errors</span>
            <span class="lb-legend__chip lb-legend__chip--system">System (non-error)</span>
            <span class="lb-legend__chip lb-legend__chip--audit">Audit</span>
        </div>

        <div class="lb-bars" style="grid-template-columns: repeat({{ $n }}, minmax(0, 1fr));">
            @foreach($bars as $bar)
                @php
                    $e = (int) ($bar['errors'] ?? 0);
                    $s = (int) ($bar['system_info'] ?? max(0, ($bar['system'] ?? 0) - $e));
                    $a = (int) ($bar['audit'] ?? 0);
                    $total = $e + $s + $a;
                    $pct = $maxTotal > 0 ? ($total / $maxTotal) * 100 : 0;
                @endphp
                <div class="lb-bar">
                    <div class="lb-bar__shell">
                        @if($total > 0)
                            <div class="lb-bar__stack" style="height: {{ max($pct, 8) }}%;">
                                @if($e > 0)
                                    <div class="lb-bar__seg lb-bar__seg--errors" style="flex: {{ $e }} 1 0;"></div>
                                @endif
                                @if($s > 0)
                                    <div class="lb-bar__seg lb-bar__seg--system" style="flex: {{ $s }} 1 0;"></div>
                                @endif
                                @if($a > 0)
                                    <div class="lb-bar__seg lb-bar__seg--audit" style="flex: {{ $a }} 1 0;"></div>
                                @endif
                            </div>
                        @else
                            <div class="lb-bar__zero"></div>
                        @endif
                    </div>
                    <span class="lb-bar__label">{{ $bar['label'] }}</span>
                    <span class="lb-bar__nums">{{ $e }} · {{ $s }} · {{ $a }}</span>
                </div>
            @endforeach
        </div>

        <div class="lb-chart__footer">
            <a href="{{ cp_route('utilities.logbook.system') }}" class="lb-header__link">
                Explore system log <span>→</span>
            </a>
        </div>
    </div>
</div>
