@php
    $pid = $pulseId ?? 'lb_pulse_'.preg_replace('/\W/', '', uniqid('', true));
@endphp

<div id="{{ $pid }}" class="logbook-pulse-root">
    <div class="flex items-center justify-between mb-1">
        <div>
            <p class="subhead mb-1 text-gray-600 dark:text-dark-175">Logbook</p>
            <h2>Live feed</h2>
            <p class="text-4xs text-gray-600 dark:text-dark-175">System + audit · filter without reload</p>
        </div>
        <a href="{{ cp_route('utilities.logbook.system') }}" class="text-sm text-gray-600 dark:text-dark-175 hover:text-gray-800 dark:hover:text-dark-100 transition flex items-center gap-1">
            View all <span>→</span>
        </a>
    </div>

    <div class="flex gap-2 mt-3 mb-4" role="tablist" aria-label="Feed filter">
        @foreach(['all' => 'All', 'errors' => 'Errors', 'info' => 'System', 'audit' => 'Audit'] as $key => $label)
            <button
                type="button"
                data-lb-filter="{{ $key }}"
                class="pill-tab @if($key === 'all') active @endif"
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="bg-gray-200 dark:bg-dark-800 rounded-xl border border-gray-400 dark:border-dark-900 divide-y divide-gray-400 dark:divide-dark-900">
        @forelse($items as $item)
            @php
                $isAudit = $item['type'] === 'audit';
                $typeLabel = $isAudit ? 'AUDIT' : 'SYSTEM';
                $meta = $item['meta'] ?? '';
                $levelLine = '';
                $chan = $meta;
                if (!$isAudit && str_contains($meta, '·')) {
                    $parts = array_map('trim', explode('·', $meta, 2));
                    $levelLine = $parts[0] ?? '';
                    $chan = $parts[1] ?? '';
                }
            @endphp
            <div
                class="logbook-pulse-row px-4 py-3 hover:bg-gray-100 dark:hover:bg-dark-750 transition group"
                data-lb-type="{{ $item['type'] }}"
                data-lb-sev="{{ $item['severity'] }}"
            >
                <div class="flex items-start gap-3">
                    <span class="shrink-0 mt-0.5 inline-flex items-center px-2 py-0.5 rounded text-4xs font-bold uppercase tracking-wide {{ $isAudit ? 'bg-gray-200 dark:bg-dark-700 text-gray-800 dark:text-dark-150' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' }}">
                        {{ $typeLabel }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-4xs text-gray-600 dark:text-dark-175">{{ $item['at']->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-dark-100 truncate">{{ $item['label'] }}</p>
                        <div class="flex items-center gap-2 mt-1 text-4xs text-gray-600 dark:text-dark-175">
                            @if($isAudit)
                                <span class="font-mono">{{ $meta }}</span>
                            @else
                                @if($levelLine !== '')
                                    @php $lvl = strtoupper($levelLine); @endphp
                                    <span class="@if(str_contains($lvl, 'ERROR')) text-red-500 @elseif(str_contains($lvl, 'DEBUG')) text-orange @endif">{{ $lvl }}</span>
                                    <span>·</span>
                                @endif
                                <span class="font-mono">{{ $chan }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-4 py-6 text-sm text-gray-600 dark:text-dark-175 text-center">No recent events yet.</div>
        @endforelse
    </div>
</div>

<script>
(function () {
    if (window.__logbookPulseFilterBound) return;
    window.__logbookPulseFilterBound = true;

    // Pulse widget filters (event delegation; works for dynamically rendered widget HTML)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-lb-filter]');
    if (!btn) return;
    const root = btn.closest('.logbook-pulse-root');
    if (!root) return;
    const mode = btn.getAttribute('data-lb-filter') || 'all';
    const rows = root.querySelectorAll('.logbook-pulse-row');
    const btns = root.querySelectorAll('[data-lb-filter]');
    rows.forEach(function (el) {
      const t = el.getAttribute('data-lb-type');
      const s = el.getAttribute('data-lb-sev');
      let show = false;
      if (mode === 'all') show = true;
      else if (mode === 'errors') show = (s === 'error');
      else if (mode === 'audit') show = (t === 'audit');
      else if (mode === 'info') show = (t === 'system' && s === 'info');
      el.classList.toggle('hidden', !show);
    });
    btns.forEach(function (b) {
      const on = b === btn;
      b.classList.toggle('active', on);
    });
  });
  
})();
</script>
