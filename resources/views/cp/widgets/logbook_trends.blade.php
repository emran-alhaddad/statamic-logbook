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
    <p class="subhead mb-1 text-gray-600 dark:text-dark-175">Logbook</p>
    <h2 class="mb-1">Volume by day</h2>
    <p class="text-4xs text-gray-600 dark:text-dark-175 mb-4">Stacked column height = day total · colours = share by type · numbers below</p>

    <div class="bg-gray-200 dark:bg-dark-800 border border-gray-400 dark:border-dark-900 rounded-xl p-5">
        <div class="flex justify-end gap-2 text-4xs text-white mb-8">
            <span class="rounded-full bg-red-500 px-2 py-1"> Errors</span>
            <span class="rounded-full bg-blue-400 px-2 py-1"> System (non-error)</span>
            <span class="rounded-full bg-orange px-2 py-1"> Audit</span>
        </div>

        <div class="grid gap-3 items-end h-36" style="grid-template-columns: repeat({{ $n }}, minmax(0, 1fr));">
            @foreach($bars as $bar)
                @php
                    $e = (int) ($bar['errors'] ?? 0);
                    $s = (int) ($bar['system_info'] ?? max(0, ($bar['system'] ?? 0) - $e));
                    $a = (int) ($bar['audit'] ?? 0);
                    $total = $e + $s + $a;
                    $pct = $maxTotal > 0 ? ($total / $maxTotal) * 100 : 0;
                @endphp
                <div class="flex flex-col items-center gap-1">
                    <div class="w-full flex flex-col justify-end" style="height: 100px;">
                        @if($total > 0)
                            <div class="w-full rounded-t-md overflow-hidden flex flex-col-reverse" style="height: {{ max($pct, 8) }}%;">
                                @if($e > 0)
                                    <div class="bg-red-500 w-full" style="flex: {{ $e }} 1 0;"></div>
                                @endif
                                @if($s > 0)
                                    <div class="bg-blue-400 w-full" style="flex: {{ $s }} 1 0;"></div>
                                @endif
                                @if($a > 0)
                                    <div class="bg-orange w-full" style="flex: {{ $a }} 1 0;"></div>
                                @endif
                            </div>
                        @else
                            <div class="w-full bg-gray-500 dark:bg-dark-700 rounded-t-md" style="height: 4px;"></div>
                        @endif
                    </div>
                    <span class="text-4xs text-gray-600 dark:text-dark-175 font-medium">{{ $bar['label'] }}</span>
                    <span class="text-4xs text-gray-500 dark:text-dark-200 tabular-nums">{{ $e }} · {{ $s }} · {{ $a }}</span>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end mt-3">
            <a href="{{ cp_route('utilities.logbook.system') }}" class="text-sm text-gray-600 dark:text-dark-175 hover:text-gray-800 dark:hover:text-dark-100 transition flex items-center gap-1">
                Explore system log <span class="ml-0.5">→</span>
            </a>
        </div>
    </div>
</div>
