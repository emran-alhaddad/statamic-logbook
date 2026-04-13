<div class="flex flex-col gap-8">
    <section>
        <div class="flex items-center justify-between mb-1">
            <div>
                <p class="subhead mb-1 text-gray-600 dark:text-dark-175">Logbook</p>
                <h2>Health overview</h2>
                <p class="text-4xs text-gray-600 dark:text-dark-175">Rolling 24h snapshot</p>
            </div>
            <a href="{{ cp_route('utilities.logbook.system') }}" class="text-sm text-gray-600 dark:text-dark-175 hover:text-gray-800 dark:hover:text-dark-100 transition flex items-center gap-1">
                Open utility <span>→</span>
            </a>
        </div>

        <div class="flex flex-col md:flex-row gap-4 my-4">
            <div class="flex-1 bg-gray-200 dark:bg-dark-800 rounded-xl border border-gray-400 dark:border-dark-900 p-5">
                <p class="text-4xs uppercase tracking-widest text-gray-600 dark:text-dark-175 font-semibold mb-3">System · 24h</p>
                <p class="text-3xl font-bold tabular-nums">{{ $systemTotal24h }}</p>
                <p class="text-4xs text-gray-600 dark:text-dark-175 mt-1">Lines written to logbook</p>
            </div>

            <div class="flex-1 bg-gray-200 dark:bg-dark-800 rounded-xl border border-red-200 dark:border-red-500/30 p-5 relative">
                <p class="text-4xs uppercase tracking-widest text-red-500 font-semibold mb-3">Errors · 24h</p>
                @if($systemErrors24h > 0)
                    <span class="inline-flex items-center rounded-full bg-red-400 px-2 py-1 text-4xs font-semibold text-white ">Attention</span>
                @else
                    <span class="absolute top-4 right-4 inline-flex items-center px-2 py-0.5 rounded-full text-4xs font-semibold bg-gray-200 text-gray-800 dark:bg-dark-700 dark:text-dark-150">OK</span>
                @endif
                <p class="text-3xl font-bold tabular-nums">{{ $systemErrors24h }}</p>
                <p class="text-4xs text-red-500 mt-1">
                    @if($systemTotal24h > 0)
                        <span class="font-semibold">{{ $errorRatio }}%</span> of system volume
                    @else
                        No system volume in window
                    @endif
                </p>
            </div>

            <div class="flex-1 bg-gray-200 dark:bg-dark-800 rounded-xl border border-gray-400 dark:border-dark-900 p-5">
                <p class="text-4xs uppercase tracking-widest text-orange font-semibold mb-3">Audit · 24h</p>
                <p class="text-3xl font-bold tabular-nums">{{ $auditTotal24h }}</p>
                <p class="text-4xs text-gray-600 dark:text-dark-175 mt-1">User &amp; content actions</p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @if(! empty($userActivity))
            <div class="bg-gray-200 dark:bg-dark-800 rounded-xl border border-gray-400 dark:border-dark-900 p-5">
                <p class="text-4xs uppercase tracking-widest text-gray-600 dark:text-dark-175 font-semibold mb-4">Team activity · 7d</p>
                <div class="space-y-3">
                    @foreach($userActivity as $u)
                        @php
                            $email = $u['email'] ?: ('User '.$u['user_id']);
                            $initial = mb_strtoupper(mb_substr($email, 0, 1));
                        @endphp
                        <div class="flex items-center gap-3 bg-gray-100 dark:bg-dark-650 rounded-lg border border-gray-400 dark:border-dark-900 px-4 py-3">
                            <div class="icon-header-avatar shrink-0">
                                <div class="icon-user-initials">{{ $initial }}</div>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-dark-100 truncate">{{ $email }}</p>
                                <p class="text-4xs text-gray-600 dark:text-dark-175">
                                    Last activity {{ $u['last_at']->diffForHumans() }} · <span class="font-semibold text-gray-800 dark:text-dark-150">{{ $u['actions'] }} actions</span>
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="bg-gray-200 dark:bg-dark-800 rounded-xl border border-gray-400 dark:border-dark-900 p-5 text-sm text-gray-600 dark:text-dark-175">No team audit activity in range.</div>
        @endif

        <div class="bg-gray-200 dark:bg-dark-800 rounded-xl border border-gray-400 dark:border-dark-900 p-5">
            <p class="text-4xs uppercase tracking-widest text-gray-600 dark:text-dark-175 font-semibold mb-4">Top audit action · 7d</p>
            <div class="flex items-center justify-between bg-gray-100 dark:bg-dark-650 rounded-lg border border-gray-400 dark:border-dark-900 px-4 py-3">
                <div>
                    <p class="text-sm font-medium font-mono text-gray-900 dark:text-dark-100">{{ optional($topAction7d)->action ?? '—' }}</p>
                    <p class="text-4xs text-gray-600 dark:text-dark-175">({{ optional($topAction7d)->c ?? 0 }})</p>
                </div>
                <a href="{{ cp_route('utilities.logbook.audit') }}" class="text-4xs text-gray-600 dark:text-dark-175 hover:text-gray-800 dark:hover:text-dark-100 transition flex items-center gap-1">
                    Audit log <span>→</span>
                </a>
            </div>
        </div>
    </section>
</div>
