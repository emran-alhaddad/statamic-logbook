{{--
    Logbook — Settings + first-run database setup ("Studio Mix" design)
    -------------------------------------------------------------------
    Two modes:
      - Not configured → split onboarding card: brand panel (progress ring,
        live-feed preview) + 3-step rail (connect DB → auto setup → live).
      - Configured     → KPI header, pill-tab panes: System (levels with real
        7-day volume bars), Audit (component tiles opening an event drawer),
        Views (page-view tracking spec), Housekeeping. Sticky savebar shows
        pending change count.

    All styling comes from the addon stylesheet ("Studio Mix settings" block
    in resources/dist/statamic-logbook.css); interactivity ships in the dist
    JS as document-level delegation — inline scripts do not survive the CP's
    Vue mount re-creating this DOM.
--}}
@extends('statamic-logbook::cp.logbook._layout', ['active' => 'settings'])

@section('panel')

@php
    /** Component colour + icon come from the shared palette, so a component
        looks the same here as it does in the audit listing. */
    $palette = \EmranAlhaddad\StatamicLogbook\Support\AuditActionPresenter::GROUP_COMPONENTS;
    $groupMeta = $pageMeta = [];
    foreach ($palette as $k => [$tint, $icon]) {
        $groupMeta[$k] = $pageMeta[$k] = [$icon, $tint];
    }
    $levelDots = [
        'emergency' => 'danger', 'alert' => 'danger', 'critical' => 'danger', 'error' => 'danger',
        'warning' => 'warn', 'notice' => 'info', 'info' => 'info', 'debug' => '',
    ];
@endphp


@if($saved)
    <div class="lb-callout lb-callout--ok" role="status">
        <span class="lb-callout__icon" aria-hidden="true">✓</span>
        <span>Settings saved. They apply from the next page load.</span>
    </div>
@endif

@if($setupError !== '')
    <div class="lb-callout lb-callout--danger" role="alert">
        <span class="lb-callout__icon" aria-hidden="true">✕</span>
        <span>{{ $setupError }}</span>
    </div>
@endif

@if($installOutput !== '')
    <div class="lb-callout lb-callout--info" role="status">
        <pre>{{ $installOutput }}</pre>
    </div>
@endif

@unless($configured)
    {{-- ======================= FIRST-RUN: ONBOARDING ======================= --}}
    <div class="lb-ob">
        <div class="lb-ob__brand">
            <div class="lb-ob__mark"><svg class="lb-i" width="19" height="19"><use href="#lbi-book"/></svg></div>
            <h2 class="lb-ob__title">Every change, on the record.</h2>
            <p class="lb-ob__sub">A complete audit trail for your Statamic site, in your own database.</p>
            <div class="lb-ob__ring">
                <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
                    <circle cx="60" cy="60" r="52" stroke="rgba(255,255,255,.12)" stroke-width="8"/>
                    <circle data-lb-ob-ring cx="60" cy="60" r="52" stroke="#818cf8" stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="326.7" stroke-dashoffset="326.7"/>
                </svg>
                <span class="lb-ob__ringn"><b data-lb-ob-ringn>0%</b><span>setup</span></span>
            </div>
            <div class="lb-ob__status" data-lb-ob-status>Waiting for connection…</div>
            <div class="lb-ob__feed" aria-hidden="true">
                <div class="lb-ob__feedrow" style="--lb-tint:var(--lb-t-indigo)"><span class="lb-icobox"><svg class="lb-i" width="11" height="11"><use href="#lbi-file"/></svg></span><span><b>Entry updated</b> · Homepage</span><span class="lb-ob__when">2m</span></div>
                <div class="lb-ob__feedrow" style="--lb-tint:var(--lb-t-rose)"><span class="lb-icobox"><svg class="lb-i" width="11" height="11"><use href="#lbi-users"/></svg></span><span><b>Failed sign-in</b> · unknown@mail.com</span><span class="lb-ob__when">9m</span></div>
                <div class="lb-ob__feedrow" style="--lb-tint:var(--lb-t-orange)"><span class="lb-icobox"><svg class="lb-i" width="11" height="11"><use href="#lbi-img"/></svg></span><span><b>Asset uploaded</b> · hero.jpg</span><span class="lb-ob__when">1h</span></div>
            </div>
        </div>
        <div class="lb-ob__main">
            <div class="lb-step is-now" data-lb-ob-step="1">
                <div class="lb-step__rail"><div class="lb-step__pin">1</div><div class="lb-step__line"></div></div>
                <div class="lb-step__body">
                    <h4>Database connection</h4>
                    <p class="lb-step__hint" data-lb-ob-hint="1">Prefilled from your <code>.env</code>. Edit anything before connecting.</p>
                    <div class="lb-step__detail">
                        <form method="POST" action="{{ cp_route('utilities.logbook.settings.database') }}" class="lb-obform" data-lb-ob>
                            @csrf
                            <div class="lb-field-row lb-field-row--host">
                                <div class="lb-field"><label class="lb-field__label" for="lb-db-host">Host</label><input id="lb-db-host" type="text" name="host" value="{{ $db['host'] }}" class="lb-input"></div>
                                <div class="lb-field"><label class="lb-field__label" for="lb-db-port">Port</label><input id="lb-db-port" type="text" name="port" value="{{ $db['port'] }}" class="lb-input"></div>
                            </div>
                            <div class="lb-field"><label class="lb-field__label" for="lb-db-database">Database</label><input id="lb-db-database" type="text" name="database" value="{{ $db['database'] }}" class="lb-input" required></div>
                            <div class="lb-field-row lb-field-row--split">
                                <div class="lb-field"><label class="lb-field__label" for="lb-db-username">Username</label><input id="lb-db-username" type="text" name="username" value="{{ $db['username'] }}" class="lb-input" required></div>
                                <div class="lb-field"><label class="lb-field__label" for="lb-db-password">Password</label><input id="lb-db-password" type="password" name="password" value="{{ $db['password'] }}" class="lb-input" autocomplete="new-password"></div>
                            </div>
                            @unless($envWritable)
                                <p class="lb-field__help">⚠ <code>.env</code> is not writable — the connection will work now, but add the <code>LOGBOOK_DB_*</code> keys manually to keep it after a restart.</p>
                            @endunless
                            <div><button type="submit" class="lb-btn lb-btn--primary">Connect &amp; install <svg class="lb-i" width="14" height="14"><use href="#lbi-arrow"/></svg></button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="lb-step" data-lb-ob-step="2">
                <div class="lb-step__rail"><div class="lb-step__pin">2</div><div class="lb-step__line"></div></div>
                <div class="lb-step__body">
                    <h4>Set everything up</h4>
                    <p class="lb-step__hint" data-lb-ob-hint="2">Runs automatically once connected.</p>
                    <div class="lb-step__detail">
                        <div class="lb-obcheck" data-lb-ob-check><svg class="lb-i" width="15" height="15"><use href="#lbi-db"/></svg> Database connection <span class="lb-obcheck__stat"></span></div>
                        <div class="lb-obcheck" data-lb-ob-check><svg class="lb-i" width="15" height="15"><use href="#lbi-server"/></svg> Create tables <span class="lb-obcheck__stat"></span></div>
                        <div class="lb-obcheck" data-lb-ob-check><svg class="lb-i" width="15" height="15"><use href="#lbi-lock"/></svg> Persist credentials to <code>.env</code> <span class="lb-obcheck__stat"></span></div>
                    </div>
                </div>
            </div>
            <div class="lb-step" data-lb-ob-step="3">
                <div class="lb-step__rail"><div class="lb-step__pin">3</div></div>
                <div class="lb-step__body">
                    <h4>Start capturing</h4>
                    <p class="lb-step__hint">Everything enabled with sensible defaults. Fine-tune in Settings after.</p>
                </div>
            </div>
        </div>
    </div>
@else
    {{-- ============================ SETTINGS ============================ --}}
    @php
        $disabledSet = array_flip($settings['disabled_events']);
        $totalEvents = 0; $enabledEvents = 0;
        foreach ($groups as $g) {
            foreach ($g['events'] as $e) {
                $totalEvents++;
                if (! isset($disabledSet[$e['class']])) $enabledEvents++;
            }
        }
        $enabledLevels = count(array_filter($settings['system_levels'] ?? []));
        $maxLevelVol = max(1, ...array_values($volumes['levels'] + ['_' => 1]));
        $maxViewVol = max(1, ...array_values($volumes['views'] + ['_' => 1]));
    @endphp

    <div class="lb-kpis">
        <div class="lb-kpis__kpi"><b data-lb-kpi-events>{{ $enabledEvents }} / {{ $totalEvents }}</b><span>events captured</span></div>
        <div class="lb-kpis__sep"></div>
        <div class="lb-kpis__kpi"><b data-lb-kpi-levels>{{ $enabledLevels }}</b><span>log levels</span></div>
        <div class="lb-kpis__sep"></div>
        <div class="lb-kpis__kpi"><b data-lb-kpi-views data-views-total="{{ number_format($volumes['views_total']) }}">{{ $settings['activity_views'] ? number_format($volumes['views_total']) : 'off' }}</b><span>page views / 7d</span></div>
        <div class="lb-kpis__search">
            <svg><use href="#lbi-search"/></svg>
            <input type="search" data-lb-search placeholder="Search events… (try: sign)" aria-label="Search events">
        </div>
    </div>

    <div class="lb-pilltabs" role="tablist" aria-label="Settings sections">
        <button type="button" class="lb-pilltab" data-lb-pilltab="sys"><svg class="lb-i" width="14" height="14"><use href="#lbi-term"/></svg> System <span class="lb-dot {{ $settings['system_logs'] ? 'lb-dot--ok' : '' }}"></span></button>
        <button type="button" class="lb-pilltab is-on" data-lb-pilltab="audit"><svg class="lb-i" width="14" height="14"><use href="#lbi-shield"/></svg> Audit <span class="lb-dot {{ $settings['audit_logs'] ? 'lb-dot--ok' : '' }}"></span></button>
        <button type="button" class="lb-pilltab" data-lb-pilltab="views"><svg class="lb-i" width="14" height="14"><use href="#lbi-eye"/></svg> Views <span class="lb-dot {{ $settings['activity_views'] ? 'lb-dot--ok' : '' }}" data-lb-views-dot></span></button>
        <button type="button" class="lb-pilltab" data-lb-pilltab="house"><svg class="lb-i" width="14" height="14"><use href="#lbi-clock"/></svg> Housekeeping</button>
    </div>

    <form method="POST" action="{{ cp_route('utilities.logbook.settings.save') }}" class="lb-settings2" data-lb-settings>
        @csrf

        {{-- ---------- System pane ---------- --}}
        <div class="lb-pane" data-lb-pane="sys">
            <div class="lb-scard lb-scard--sys {{ $settings['system_logs'] ? '' : 'is-off' }}" data-lb-section>
                <div class="lb-scard__head">
                    <span class="lb-icobox lb-icobox--lg" style="--lb-tint:var(--lb-t-sky)"><svg class="lb-i" width="17" height="17"><use href="#lbi-term"/></svg></span>
                    <div class="lb-scard__titles"><h4>System Logs</h4><p>Application messages from Laravel, Statamic and your own code.</p></div>
                    <label class="lb-sw"><input type="checkbox" name="system_logs" value="1" @checked($settings['system_logs']) data-lb-section-master aria-label="Capture system logs"><span class="lb-sw__tr"></span><span class="lb-sw__kn"></span></label>
                </div>
                <div class="lb-scard__body">
                    @foreach($levels as $level)
                        @php $n = $volumes['levels'][$level] ?? 0; @endphp
                        <div class="lb-lvlrow">
                            <label class="lb-sw lb-sw--sm"><input type="checkbox" name="levels[{{ $level }}]" value="1" @checked($settings['system_levels'][$level] ?? true) data-lb-level aria-label="Capture {{ $level }}"><span class="lb-sw__tr"></span><span class="lb-sw__kn"></span></label>
                            <span class="lb-lvlrow__name"><span class="lb-dot {{ ($levelDots[$level] ?? '') !== '' ? 'lb-dot--'.$levelDots[$level] : '' }}"></span>{{ $level }}</span>
                            <span class="lb-volbar" style="--lb-tint:var(--lb-t-{{ ['danger' => 'rose', 'warn' => 'amber', 'info' => 'sky', '' => 'indigo'][$levelDots[$level] ?? ''] }})"><i style="width:{{ (int) round(100 * $n / $maxLevelVol) }}%"></i></span>
                            <span class="lb-voln">{{ $n > 0 ? number_format($n) : '—' }}</span>
                        </div>
                    @endforeach
                    <div class="lb-field" style="margin-top:10px">
                        <label class="lb-field__label" for="lb-ignore-channels">Ignored channels</label>
                        <input id="lb-ignore-channels" type="text" name="ignore_channels_extra" value="{{ $settings['ignore_channels_extra'] }}" placeholder="e.g. deprecations, queries" class="lb-input lb-field-md">
                        <p class="lb-field__help">Comma-separated log channels to skip, on top of the defaults.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Audit pane ---------- --}}
        <div class="lb-pane is-on" data-lb-pane="audit">
            <div class="{{ $settings['audit_logs'] ? '' : 'is-off' }} lb-auditwrap" data-lb-section>
                <div class="lb-scard__head lb-scard__head--bare">
                    <span class="lb-icobox lb-icobox--lg" style="--lb-tint:var(--lb-t-indigo)"><svg class="lb-i" width="17" height="17"><use href="#lbi-shield"/></svg></span>
                    <div class="lb-scard__titles"><h4>Audit Logs</h4><p>Who did what in the CMS — open a component to pick events.</p></div>
                    <label class="lb-sw"><input type="checkbox" name="audit_logs" value="1" @checked($settings['audit_logs']) data-lb-section-master aria-label="Capture audit logs"><span class="lb-sw__tr"></span><span class="lb-sw__kn"></span></label>
                </div>
                <div class="lb-scard__body lb-scard__body--bare">
                    <div class="lb-tiles">
                        @foreach($groups as $key => $group)
                            @php
                                [$gi, $gt] = $groupMeta[$key] ?? ['lbi-server', 'indigo'];
                                $total = count($group['events']);
                                $on = collect($group['events'])->reject(fn ($e) => isset($disabledSet[$e['class']]))->count();
                                $frac = $total > 0 ? $on / $total : 0;
                                $gvol = $volumes['groups'][$key] ?? 0;
                            @endphp
                            <button type="button" class="lb-tile" style="--lb-tint:var(--lb-t-{{ $gt }})"
                                    data-lb-tile="{{ $key }}"
                                    data-lb-events="{{ mb_strtolower(collect($group['events'])->pluck('label')->implode('|')) }}">
                                <div class="lb-tile__top">
                                    <span class="lb-icobox lb-icobox--lg"><svg class="lb-i" width="16" height="16"><use href="#{{ $gi }}"/></svg></span>
                                    <span class="lb-tile__ring">
                                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                                            <circle class="lb-ringbg" cx="20" cy="20" r="16" stroke-width="4" fill="none"/>
                                            <circle class="lb-ringfg" cx="20" cy="20" r="16" stroke-width="4" fill="none" stroke-linecap="round" stroke-dasharray="100.5" stroke-dashoffset="{{ number_format(100.5 * (1 - $frac), 1, '.', '') }}" data-lb-ringfg="{{ $key }}"/>
                                        </svg>
                                        <span class="lb-tile__ringn" data-lb-ringn="{{ $key }}">{{ $on }}/{{ $total }}</span>
                                    </span>
                                </div>
                                <div><h5>{{ $group['label'] }}</h5><p class="lb-tile__sub">{{ $gvol > 0 ? '~'.number_format($gvol).' rows/7d' : 'no rows in 7d' }}</p></div>
                                <span class="lb-tile__hit" data-lb-hit hidden></span>
                            </button>
                        @endforeach
                    </div>
                    <div class="lb-field" style="margin-top:16px">
                        <label class="lb-field__label" for="lb-ignore-fields">Ignored fields</label>
                        <input id="lb-ignore-fields" type="text" name="ignore_fields_extra" value="{{ $settings['ignore_fields_extra'] }}" placeholder="e.g. internal_notes, cache_key" class="lb-input lb-field-md">
                        <p class="lb-field__help">Comma-separated field names left out of change diffs, on top of the defaults.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Views pane ---------- --}}
        <div class="lb-pane" data-lb-pane="views">
            <div class="lb-scard">
                <div class="lb-scard__head lb-scard__head--flush">
                    <span class="lb-icobox lb-icobox--lg" style="--lb-tint:var(--lb-t-violet)"><svg class="lb-i" width="18" height="18"><use href="#lbi-eye"/></svg></span>
                    <div class="lb-scard__titles">
                        <h4>Page Views</h4>
                        <p>Record who opened what in the CP. Reads, not changes: higher volume, tighter controls.</p>
                    </div>
                    <label class="lb-sw"><input type="checkbox" name="activity_views" value="1" @checked($settings['activity_views']) data-lb-views-master aria-label="Track page views"><span class="lb-sw__tr"></span><span class="lb-sw__kn"></span></label>
                </div>
                <div class="lb-views2 {{ $settings['activity_views'] ? '' : 'is-off' }}" data-lb-views-body>
                    <div class="lb-vgrid">
                        <div>
                            <p class="lb-vlabel">Tracked pages</p>
                            @foreach($viewPages as $key => $label)
                                @php
                                    [$pi, $pt] = $pageMeta[$key] ?? ['lbi-file', 'indigo'];
                                    $pv = $volumes['views'][$key] ?? 0;
                                @endphp
                                <div class="lb-vrow" style="--lb-tint:var(--lb-t-{{ $pt }})">
                                    <span class="lb-icobox"><svg class="lb-i" width="13" height="13"><use href="#{{ $pi }}"/></svg></span>
                                    <span class="lb-vrow__nm">{{ $label }}</span>
                                    <span class="lb-vrow__vol"><span class="lb-volbar"><i style="width:{{ (int) round(100 * $pv / $maxViewVol) }}%"></i></span><span class="lb-voln">{{ $pv > 0 ? number_format($pv) : '—' }}</span></span>
                                    <label class="lb-sw lb-sw--sm"><input type="checkbox" name="view_pages[{{ $key }}]" value="1" @checked($settings['view_pages'][$key] ?? true) data-lb-vpage="{{ $pv }}" aria-label="Track {{ $label }}"><span class="lb-sw__tr"></span><span class="lb-sw__kn"></span></label>
                                </div>
                            @endforeach
                        </div>
                        <div class="lb-vside">
                            <div class="lb-scard lb-scard--flat">
                                <p class="lb-vlabel">Recorded volume</p>
                                <div class="lb-vproj"><b data-lb-vproj>{{ number_format($volumes['views_total']) }}</b><span>rows in the last 7 days from selected pages</span></div>
                            </div>
                            <div class="lb-field">
                                <label class="lb-field__label" for="lb-view-dedupe">Ignore repeat views within</label>
                                @php $dedupe = (int) $settings['view_dedupe_minutes']; @endphp
                                <select id="lb-view-dedupe" name="view_dedupe_minutes" class="lb-input lb-select" style="max-width:220px">
                                    @foreach([0 => 'Off — record every open', 1 => '1 minute', 5 => '5 minutes', 15 => '15 minutes', 60 => '1 hour'] as $mins => $optLabel)
                                        <option value="{{ $mins }}" @selected($dedupe === $mins)>{{ $optLabel }}</option>
                                    @endforeach
                                    @if(! in_array($dedupe, [0, 1, 5, 15, 60], true))
                                        <option value="{{ $dedupe }}" selected>{{ $dedupe }} minutes</option>
                                    @endif
                                </select>
                                <p class="lb-field__help">Refreshing the same page repeatedly counts once per window.</p>
                            </div>
                            <div class="lb-field">
                                <span class="lb-field__label">Who gets tracked</span>
                                <div class="lb-optchips">
                                    <label class="lb-optchip"><input type="checkbox" name="view_excluded_roles[]" value="{{ $superSentinel }}" @checked(in_array($superSentinel, $settings['view_excluded_roles'], true))>Exclude super admins</label>
                                    @foreach($roles as $handle => $title)
                                        <label class="lb-optchip"><input type="checkbox" name="view_excluded_roles[]" value="{{ $handle }}" @checked(in_array($handle, $settings['view_excluded_roles'], true))>Exclude role: {{ $title }}</label>
                                    @endforeach
                                </div>
                                <p class="lb-field__help">Unchecked means everyone is tracked.</p>
                            </div>
                            <div class="lb-field">
                                <label class="lb-field__label" for="lb-view-retention">Keep view rows for</label>
                                <div class="lb-row">
                                    <input id="lb-view-retention" class="lb-input" type="number" name="view_retention_days" min="1" max="3650" value="{{ $settings['view_retention_days'] }}" style="width:90px">
                                    <span class="lb-field__help">days, separate from the audit retention ({{ $settings['retention_days'] }}).</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Housekeeping pane ---------- --}}
        <div class="lb-pane" data-lb-pane="house">
            <div class="lb-scard lb-scard--house">
                <div class="lb-scard__head">
                    <span class="lb-icobox lb-icobox--lg" style="--lb-tint:var(--lb-t-amber)"><svg class="lb-i" width="17" height="17"><use href="#lbi-clock"/></svg></span>
                    <div class="lb-scard__titles"><h4>Housekeeping</h4><p>Retention and storage status.</p></div>
                </div>
                <div class="lb-scard__body">
                    <div class="lb-field">
                        <label class="lb-field__label" for="lb-retention">Keep logs for</label>
                        <div class="lb-row">
                            <input id="lb-retention" class="lb-input" type="number" name="retention_days" min="1" max="3650" value="{{ $settings['retention_days'] }}" style="width:100px">
                            <span class="lb-field__help">days — older rows are removed by Prune.</span>
                        </div>
                    </div>
                    <div class="lb-status-line">
                        <span class="lb-status-line__item"><span class="lb-dot {{ $dbOk ? 'lb-dot--ok' : 'lb-dot--warn' }}" aria-hidden="true"></span>Database {{ $dbOk ? 'connected' : 'unreachable' }}</span>
                        <span class="lb-status-line__item"><span class="lb-dot {{ $installed ? 'lb-dot--ok' : 'lb-dot--warn' }}" aria-hidden="true"></span>Tables {{ $installed ? 'installed' : 'missing' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Sticky savebar ---------- --}}
        <div class="lb-fbar" data-lb-fbar>
            <svg class="lb-i" width="15" height="15"><use href="#lbi-act"/></svg>
            <span class="lb-fbar__n"><b data-lb-fbar-n>0 changes</b> · applies on the next page load.</span>
            <span class="lb-fbar__spacer"></span>
            <button type="button" class="lb-btn lb-fbar__ghost" data-lb-fbar-discard>Discard</button>
            <button type="submit" class="lb-btn lb-btn--primary">Save changes</button>
        </div>

        {{-- ---------- Event drawer (one pane per group) ---------- --}}
        <div class="lb-veil" data-lb-veil></div>
        <aside class="lb-drawer" data-lb-drawer aria-label="Component events">
            @foreach($groups as $key => $group)
                @php [$gi, $gt] = $groupMeta[$key] ?? ['lbi-server', 'indigo']; @endphp
                <div class="lb-drawer__pane" data-lb-drawer-pane="{{ $key }}" hidden>
                    <div class="lb-drawer__head">
                        <span class="lb-icobox lb-icobox--lg" style="--lb-tint:var(--lb-t-{{ $gt }})"><svg class="lb-i" width="14" height="14"><use href="#{{ $gi }}"/></svg></span>
                        <h5>{{ $group['label'] }}</h5>
                        <button type="button" class="lb-drawer__x" data-lb-drawer-close aria-label="Close"><svg class="lb-i" width="16" height="16"><use href="#lbi-x"/></svg></button>
                    </div>
                    <div class="lb-drawer__quick">
                        <button type="button" class="lb-btn lb-btn--sm" data-lb-dq="all">All</button>
                        <button type="button" class="lb-btn lb-btn--sm" data-lb-dq="none">None</button>
                        <button type="button" class="lb-btn lb-btn--sm" data-lb-dq="invert">Invert</button>
                    </div>
                    <div class="lb-drawer__body">
                        @foreach($group['events'] as $event)
                            <div class="lb-evrow" style="--lb-tint:var(--lb-t-{{ $gt }})">
                                <span class="lb-evrow__name">{{ $event['label'] }}</span>
                                <label class="lb-sw lb-sw--sm"><input type="checkbox" name="events[]" value="{{ $event['class'] }}" data-lb-ev="{{ $key }}" @checked(! isset($disabledSet[$event['class']])) aria-label="Capture {{ $event['label'] }}"><span class="lb-sw__tr"></span><span class="lb-sw__kn"></span></label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </aside>
    </form>
@endunless

@endsection
