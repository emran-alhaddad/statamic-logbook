{{--
    Logbook — Overview card widget
    --------------------------------------------------------------
    Styling comes from the addon-shipped stylesheet
    (resources/dist/statamic-logbook.css), auto-registered via
    $stylesheets on LogbookServiceProvider. We deliberately avoid
    CP Tailwind utilities that get purged by the host CP's JIT
    build (dark:*, bg-dark-*, text-4xs, tracking-widest, bg-orange,
    subhead, icon-header-avatar, etc.).
--}}
<div class="lb-stack">
    <section>
        <div class="lb-header">
            <div>
                <p class="lb-header__kicker">Logbook</p>
                <h2 class="lb-header__title">Health overview</h2>
                <p class="lb-header__meta">Rolling 24h snapshot</p>
            </div>
            <a href="{{ cp_route('utilities.logbook.system') }}" class="lb-header__link">
                Open utility <span>→</span>
            </a>
        </div>

        <div class="lb-cards">
            <div class="lb-card">
                <p class="lb-card__label">System · 24h</p>
                <p class="lb-card__value">{{ $systemTotal24h }}</p>
                <p class="lb-card__meta">Lines written to logbook</p>
            </div>

            <div class="lb-card lb-card--danger">
                <p class="lb-card__label lb-card__label--danger">Errors · 24h</p>
                @if($systemErrors24h > 0)
                    <span class="lb-card__badge lb-card__badge--danger">Attention</span>
                @else
                    <span class="lb-card__badge lb-card__badge--ok">OK</span>
                @endif
                <p class="lb-card__value">{{ $systemErrors24h }}</p>
                <p class="lb-card__meta lb-card__meta--danger">
                    @if($systemTotal24h > 0)
                        <strong>{{ $errorRatio }}%</strong> of system volume
                    @else
                        No system volume in window
                    @endif
                </p>
            </div>

            <div class="lb-card">
                <p class="lb-card__label lb-card__label--warn">Audit · 24h</p>
                <p class="lb-card__value">{{ $auditTotal24h }}</p>
                <p class="lb-card__meta">User &amp; content actions</p>
            </div>
        </div>
    </section>

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
                            <div class="lb-avatar">{{ $initial }}</div>
                            <div class="lb-user-meta">
                                <p class="lb-user-email">{{ $email }}</p>
                                <p class="lb-user-sub">
                                    Last activity {{ $u['last_at']->diffForHumans() }} ·
                                    <strong>{{ $u['actions'] }} actions</strong>
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="lb-panel">
                <p class="lb-panel__empty">No team audit activity in range.</p>
            </div>
        @endif

        <div class="lb-panel">
            <p class="lb-panel__label">Top audit action · 7d</p>
            <div class="lb-action-row">
                <div>
                    <p class="lb-action-row__name">{{ optional($topAction7d)->action ?? '—' }}</p>
                    <p class="lb-action-row__count">({{ optional($topAction7d)->c ?? 0 }})</p>
                </div>
                <a href="{{ cp_route('utilities.logbook.audit') }}" class="lb-action-row__link">
                    Audit log <span>→</span>
                </a>
            </div>
        </div>
    </section>
</div>
