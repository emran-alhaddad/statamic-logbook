<?php

namespace EmranAlhaddad\StatamicLogbook\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use EmranAlhaddad\StatamicLogbook\Support\AuditActionPresenter;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use EmranAlhaddad\StatamicLogbook\Support\UserPrefsRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Throwable;

class LogbookUtilityController
{
    public function __invoke(Request $request)
    {
        // default page: system
        return redirect()->to(cp_route('utilities.logbook.system'));
    }

    public function system(Request $request)
    {
        $conn = DbConnectionResolver::resolve();

        $q = DB::connection($conn)->table('logbook_system_logs');

        if ($from = $request->get('from')) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('created_at', '<=', $to);
        if ($level = $request->get('level')) $q->where('level', $level);
        if ($channel = $request->get('channel')) $q->where('channel', $channel);

        if ($search = trim((string) $request->get('q'))) {
            $q->where('message', 'like', "%{$search}%");
        }

        [$sort, $dir] = $this->resolveSort(
            $request,
            ['id', 'created_at', 'level', 'channel', 'user_id'],
            'id',
            'desc'
        );

        $perPage = $this->resolvePerPage($request);

        $logs = $q->orderBy($sort, $dir)->orderByDesc('id')->paginate($perPage)->withQueryString();

        $levels = DB::connection($conn)->table('logbook_system_logs')
            ->select('level')->distinct()->orderBy('level')->pluck('level')->all();

        $channels = DB::connection($conn)->table('logbook_system_logs')
            ->select('channel')->whereNotNull('channel')->distinct()->orderBy('channel')->pluck('channel')->all();

        $stats = $this->systemStats($conn);
        return view('statamic-logbook::cp.logbook.system', [
            'filters' => $request->all(),
            'logs' => $logs,
            'stats' => $stats,
            'levels' => $levels,
            'channels' => $channels,
            'sort' => $sort,
            'dir'  => $dir,
            'perPage' => $perPage,
            'perPageOptions' => $this->perPageOptions(),
        ]);
    }

    public function audit(Request $request)
    {
        $conn = DbConnectionResolver::resolve();

        $q = DB::connection($conn)->table('logbook_audit_logs');

        if ($from = $request->get('from')) {
            $q->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        if ($action = $request->get('action')) {
            $q->where('action', $action);
        }

        if ($subject = $request->get('subject_type')) {
            $q->where('subject_type', $subject);
        }

        if ($user = $request->get('user_id')) {
            $q->where('user_id', $user);
        }

        if ($search = trim((string) $request->get('q'))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('subject_title', 'like', "%{$search}%")
                    ->orWhere('subject_handle', 'like', "%{$search}%");
            });
        }

        [$sort, $dir] = $this->resolveSort(
            $request,
            ['id', 'created_at', 'action', 'subject_type', 'user_email'],
            'id',
            'desc'
        );

        $perPage = $this->resolvePerPage($request);

        $logs = $q->orderBy($sort, $dir)->orderByDesc('id')->paginate($perPage)->withQueryString();

        $actions = DB::connection($conn)->table('logbook_audit_logs')
            ->select('action')->distinct()->orderBy('action')->pluck('action')->all();

        $subjects = DB::connection($conn)->table('logbook_audit_logs')
            ->select('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type')->all();

        $stats = $this->auditStats($conn);

        return view('statamic-logbook::cp.logbook.audit', [
            'filters' => $request->all(),
            'logs' => $logs,
            'stats' => $stats,
            'actions' => $actions,
            'subjects' => $subjects,
            'sort' => $sort,
            'dir'  => $dir,
            'perPage' => $perPage,
            'perPageOptions' => $this->perPageOptions(),
        ]);
    }

    /**
     * Unified timeline — interleaved system + audit events grouped by day.
     *
     * Query parameters:
     *   from, to                — ISO date bounds
     *   q                       — text filter (system message OR audit subject)
     *   types[]                 — subset of ['system', 'audit']; default both
     *   sev[]                   — subset of ['error', 'warn', 'info']; default all
     */
    public function timeline(Request $request)
    {
        $conn = DbConnectionResolver::resolve();

        $from = $request->get('from');
        $to   = $request->get('to');
        $q    = trim((string) $request->get('q'));
        $types = (array) $request->get('types', ['system', 'audit']);
        $types = array_values(array_intersect($types, ['system', 'audit'])) ?: ['system', 'audit'];
        $sev = (array) $request->get('sev', []);
        $sev = array_values(array_intersect($sev, ['error', 'warn', 'info']));

        $limit = max(20, min(500, (int) $request->get('limit', 200)));

        $items = [];

        if (in_array('system', $types, true)) {
            $sys = DB::connection($conn)->table('logbook_system_logs');
            if ($from) $sys->whereDate('created_at', '>=', $from);
            if ($to)   $sys->whereDate('created_at', '<=', $to);
            if ($q !== '') $sys->where('message', 'like', "%{$q}%");
            if (! empty($sev)) {
                $levels = [];
                if (in_array('error', $sev, true)) $levels = array_merge($levels, ['emergency', 'alert', 'critical', 'error']);
                if (in_array('warn', $sev, true))  $levels[] = 'warning';
                if (in_array('info', $sev, true))  $levels = array_merge($levels, ['notice', 'info', 'debug']);
                if (! empty($levels)) $sys->whereIn('level', $levels);
            }

            foreach ($sys->orderByDesc('id')->limit($limit)->get() as $r) {
                $level = strtolower((string) ($r->level ?? 'info'));
                $severity = in_array($level, ['emergency', 'alert', 'critical', 'error'], true)
                    ? 'error'
                    : ($level === 'warning' ? 'warn' : 'info');
                $items[] = [
                    'id'       => 'sys-'.$r->id,
                    'type'     => 'system',
                    'severity' => $severity,
                    'label'    => $level,
                    'message'  => (string) ($r->message ?? ''),
                    'meta'     => trim(strtoupper($level).' · '.(string) ($r->channel ?? 'app'), ' ·'),
                    'user'     => $r->user_id ?? null,
                    'at'       => \Carbon\Carbon::parse($r->created_at),
                ];
            }
        }

        if (in_array('audit', $types, true) && (empty($sev) || in_array('audit', $sev, true))) {
            $aud = DB::connection($conn)->table('logbook_audit_logs');
            if ($from) $aud->whereDate('created_at', '>=', $from);
            if ($to)   $aud->whereDate('created_at', '<=', $to);
            if ($q !== '') {
                $aud->where(function ($qq) use ($q) {
                    $qq->where('subject_title', 'like', "%{$q}%")
                        ->orWhere('subject_handle', 'like', "%{$q}%")
                        ->orWhere('action', 'like', "%{$q}%");
                });
            }

            foreach ($aud->orderByDesc('id')->limit($limit)->get() as $r) {
                $action = (string) ($r->action ?? '');
                // Run the raw event string through the same presenter the
                // Audit Logs page uses so the Timeline displays
                // "User updated" instead of `statamic.user.updated`,
                // "Entry created" instead of `statamic.entry.created`,
                // etc. The raw string is still exposed via `actionRaw`
                // for the chip's tooltip so ops users can grep by
                // machine name.
                $humanLabel = AuditActionPresenter::label($action);
                $variant    = AuditActionPresenter::variant($action);
                $changeHint = AuditActionPresenter::changeSummary($r->changes ?? null);

                // Prefer the subject title / handle for the row message;
                // fall back to the humanised label when the audit row
                // has no subject metadata (login / logout events).
                $messageBase = (string) ($r->subject_title ?: $r->subject_handle ?: '');
                if ($messageBase === '') {
                    $messageBase = $humanLabel;
                }
                if ($changeHint !== null && $changeHint !== '') {
                    $messageBase = trim($messageBase.' · '.$changeHint);
                }

                $items[] = [
                    'id'        => 'aud-'.$r->id,
                    'type'      => 'audit',
                    'severity'  => 'audit',
                    'label'     => $humanLabel,
                    'actionRaw' => $action,
                    'variant'   => $variant,
                    'message'   => $messageBase,
                    'meta'      => trim(($r->subject_type ?? '').' · '.($r->subject_handle ?? ''), ' ·'),
                    'user'      => $r->user_email ?? $r->user_id ?? null,
                    'at'        => \Carbon\Carbon::parse($r->created_at),
                ];
            }
        }

        usort($items, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);
        $items = array_slice($items, 0, $limit);

        // Group by calendar day (YYYY-MM-DD) for the rendered timeline rails.
        $grouped = [];
        foreach ($items as $it) {
            $day = $it['at']->toDateString();
            $grouped[$day][] = $it;
        }

        return view('statamic-logbook::cp.logbook.timeline', [
            'filters'   => $request->all(),
            'grouped'   => $grouped,
            'types'     => $types,
            'sev'       => $sev,
            'itemCount' => count($items),
            'limit'     => $limit,
        ]);
    }

    /**
     * JSON endpoint: newer system log rows since `after_id`. Used by the
     * live-tail toggle on the system logs page.
     */
    public function systemJson(Request $request): JsonResponse
    {
        $conn = DbConnectionResolver::resolve();
        $afterId = (int) $request->get('after_id', 0);
        $limit = max(1, min(100, (int) $request->get('limit', 25)));

        $q = DB::connection($conn)->table('logbook_system_logs');
        if ($afterId > 0) $q->where('id', '>', $afterId);

        if ($level = $request->get('level')) $q->where('level', $level);
        if ($channel = $request->get('channel')) $q->where('channel', $channel);
        if ($search = trim((string) $request->get('q'))) $q->where('message', 'like', "%{$search}%");

        $rows = $q->orderByDesc('id')->limit($limit)->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'         => (int) $r->id,
                'created_at' => (string) $r->created_at,
                'level'      => (string) $r->level,
                'channel'    => (string) ($r->channel ?? ''),
                'message'    => (string) ($r->message ?? ''),
                'user_id'    => $r->user_id,
            ];
        }

        // Reverse so the caller can append oldest-to-newest in the DOM.
        $out = array_reverse($out);

        $latest = $rows->first();

        return response()->json([
            'rows'        => $out,
            'latest_id'   => $latest ? (int) $latest->id : $afterId,
            'fetched_at'  => now()->toIso8601String(),
            'count'       => count($out),
        ]);
    }

    /**
     * JSON endpoint: newer audit log rows since `after_id`. Used by the
     * live-tail toggle on the audit logs page.
     */
    public function auditJson(Request $request): JsonResponse
    {
        $conn = DbConnectionResolver::resolve();
        $afterId = (int) $request->get('after_id', 0);
        $limit = max(1, min(100, (int) $request->get('limit', 25)));

        $q = DB::connection($conn)->table('logbook_audit_logs');
        if ($afterId > 0) $q->where('id', '>', $afterId);

        if ($action = $request->get('action')) $q->where('action', $action);
        if ($subject = $request->get('subject_type')) $q->where('subject_type', $subject);
        if ($search = trim((string) $request->get('q'))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('subject_title', 'like', "%{$search}%")
                    ->orWhere('subject_handle', 'like', "%{$search}%");
            });
        }

        $rows = $q->orderByDesc('id')->limit($limit)->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => (int) $r->id,
                'created_at'     => (string) $r->created_at,
                'action'         => (string) ($r->action ?? ''),
                'subject_type'   => (string) ($r->subject_type ?? ''),
                'subject_title'  => (string) ($r->subject_title ?? ''),
                'subject_handle' => (string) ($r->subject_handle ?? ''),
                'user'           => $r->user_email ?? $r->user_id ?? null,
            ];
        }
        $out = array_reverse($out);

        $latest = $rows->first();

        return response()->json([
            'rows'       => $out,
            'latest_id'  => $latest ? (int) $latest->id : $afterId,
            'fetched_at' => now()->toIso8601String(),
            'count'      => count($out),
        ]);
    }

    /**
     * Allowed per-page options for the utility tables. Kept tight so the
     * server never has to deal with arbitrary user-supplied bounds.
     *
     * @return array<int,int>
     */
    protected function perPageOptions(): array
    {
        return [25, 50, 100, 200];
    }

    /**
     * Clamp `per_page` request input to the allowed set, defaulting to 50.
     */
    protected function resolvePerPage(Request $request): int
    {
        $raw = (int) $request->get('per_page', 50);
        $allowed = $this->perPageOptions();
        return in_array($raw, $allowed, true) ? $raw : 50;
    }

    /**
     * Resolve a safe sort column / direction pair from request input.
     *
     * @param  array<int,string>  $allowed
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(Request $request, array $allowed, string $default, string $defaultDir = 'desc'): array
    {
        $sort = (string) $request->get('sort', $default);
        if (! in_array($sort, $allowed, true)) {
            $sort = $default;
        }

        $dir = strtolower((string) $request->get('dir', $defaultDir));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaultDir;
        }

        return [$sort, $dir];
    }

    public function exportSystemCsv(Request $request): StreamedResponse
    {
        $conn = DbConnectionResolver::resolve();

        $q = DB::connection($conn)->table('logbook_system_logs');

        if ($from = $request->get('from')) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('created_at', '<=', $to);
        if ($level = $request->get('level')) $q->where('level', $level);
        if ($channel = $request->get('channel')) $q->where('channel', $channel);

        if ($search = trim((string) $request->get('q'))) {
            $q->where('message', 'like', "%{$search}%");
        }

        $filename = 'logbook_system_logs_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($q) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel (Arabic safe)
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, [
                'id',
                'created_at',
                'level',
                'channel',
                'message',
                'context',
                'request_id',
                'user_id',
                'ip',
                'method',
                'url',
                'user_agent'
            ]);

            $q->orderBy('id')->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        $r->created_at,
                        $r->level,
                        $r->channel,
                        $r->message,
                        $r->context,
                        $r->request_id,
                        $r->user_id,
                        $r->ip,
                        $r->method,
                        $r->url,
                        $r->user_agent,
                    ]);
                }
            });

            fclose($out);
        }, 200, $headers);
    }

    public function exportAuditCsv(Request $request): StreamedResponse
    {
        $conn = DbConnectionResolver::resolve();

        $q = DB::connection($conn)->table('logbook_audit_logs');

        if ($from = $request->get('from')) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('created_at', '<=', $to);
        if ($action = $request->get('action')) $q->where('action', $action);
        if ($subject = $request->get('subject_type')) $q->where('subject_type', $subject);
        if ($user = $request->get('user_id')) $q->where('user_id', $user);

        if ($search = trim((string) $request->get('q'))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('subject_title', 'like', "%{$search}%")
                    ->orWhere('subject_handle', 'like', "%{$search}%");
            });
        }

        $filename = 'logbook_audit_logs_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($q) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for Excel (Arabic safe)
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, [
                'id',
                'created_at',
                'action',
                'user_id',
                'user_email',
                'subject_type',
                'subject_id',
                'subject_handle',
                'subject_title',
                'changes',
                'meta',
                'ip',
                'user_agent'
            ]);

            $q->orderBy('id')->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        $r->created_at,
                        $r->action,
                        $r->user_id,
                        $r->user_email,
                        $r->subject_type,
                        $r->subject_id,
                        $r->subject_handle,
                        $r->subject_title,
                        $r->changes,
                        $r->meta,
                        $r->ip,
                        $r->user_agent,
                    ]);
                }
            });

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Read one or all keys of the current user's logbook prefs.
     *
     *   GET /utilities/logbook/prefs            → { prefs: {...} }
     *   GET /utilities/logbook/prefs/{key}      → { key, value }
     *
     * Unauthenticated / missing user → 403 so the client falls back to
     * localStorage gracefully. Table unavailable → {} (empty).
     */
    public function getPrefs(Request $request, ?string $key = null): JsonResponse
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            return response()->json(['ok' => false, 'message' => 'Not authenticated.'], 403);
        }

        if ($key === null) {
            return response()->json([
                'ok' => true,
                'prefs' => UserPrefsRepository::all($userId),
            ]);
        }

        return response()->json([
            'ok' => true,
            'key' => $key,
            'value' => UserPrefsRepository::get($userId, $key),
        ]);
    }

    /**
     * Write a single pref key. Body: { value: <any JSON> }.
     *
     *   PUT /utilities/logbook/prefs/{key}
     *
     * Values are JSON-serialised server-side so callers can post
     * nested arrays/objects directly — e.g. a list of saved filter
     * presets — without any client-side encoding.
     */
    public function setPref(Request $request, string $key): JsonResponse
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            return response()->json(['ok' => false, 'message' => 'Not authenticated.'], 403);
        }

        $trimmedKey = trim($key);
        if ($trimmedKey === '' || strlen($trimmedKey) > 64) {
            return response()->json(['ok' => false, 'message' => 'Invalid key.'], 422);
        }

        $ok = UserPrefsRepository::set($userId, $trimmedKey, $request->input('value'));

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'message' => 'Preference storage unavailable — logbook_user_prefs table may be missing. Run: php artisan logbook:install',
            ], 503);
        }

        return response()->json(['ok' => true, 'key' => $trimmedKey]);
    }

    /**
     * Remove a single pref key. Useful for "reset density" / "clear
     * saved presets" UI affordances.
     */
    public function forgetPref(Request $request, string $key): JsonResponse
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            return response()->json(['ok' => false, 'message' => 'Not authenticated.'], 403);
        }

        $ok = UserPrefsRepository::forget($userId, $key);
        return response()->json(['ok' => $ok, 'key' => $key]);
    }

    /**
     * Resolve the current CP user's id as a string, or null if not
     * authenticated. Statamic user ids are UUID-like strings.
     */
    protected function currentUserId(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $id = method_exists($user, 'id') ? $user->id() : ($user->id ?? null);
        if (! is_string($id) && ! is_int($id)) {
            return null;
        }
        $id = (string) $id;
        return $id === '' ? null : $id;
    }

    public function runPrune(Request $request): JsonResponse
    {
        return $this->runCommand('logbook:prune');
    }

    public function runFlushSpool(Request $request): JsonResponse
    {
        return $this->runCommand('logbook:flush-spool');
    }

    protected function runCommand(string $command): JsonResponse
    {
        try {
            $exitCode = Artisan::call($command);
            $output = $this->scrubPaths(trim((string) Artisan::output()));

            if ($exitCode === 0) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Command completed successfully.',
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'output' => $output,
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => 'Command failed.',
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $output,
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $this->scrubPaths($e->getMessage()),
                'command' => $command,
                'output' => '',
            ], 500);
        }
    }

    /**
     * Strip server-side filesystem paths from any string that may end up in
     * the Control Panel. Console commands go through `Artisan::output()` and
     * top-level exceptions surface `$e->getMessage()`, both of which can
     * embed absolute paths, stack frames, or `/var/www/...`-style roots from
     * Laravel's own error messages. Anything matching a path-like pattern is
     * replaced with its basename so operators still have enough to grep
     * logs, without exposing server layout to CP users.
     */
    protected function scrubPaths(string $text): string
     {
         if ($text === '') {
             return $text;
         }

        // POSIX-style absolute paths with at least two segments:
        //   /a/b/c/file.ext → file.ext
        //   /tmp/foo.log    → foo.log
        $text = preg_replace_callback(
            '#(?:/[^/\s"\'<>:()]+){1,}/([^/\s"\'<>:()]+)#',
            static fn (array $m): string => $m[1],
            $text
        ) ?? $text;

        // Windows-style paths: C:\a\b\file.ext → file.ext
        return preg_replace_callback(
            '#[A-Za-z]:(?:\\\\[^\\\\\s"\'<>:()]+){1,}\\\\([^\\\\\s"\'<>:()]+)#',
            static fn (array $m): string => $m[1],
            $text
        ) ?? $text;
    }

    protected function systemStats(string $conn): array
    {
        $now = now();
        $since24h = $now->copy()->subHours(24);
        $since7d  = $now->copy()->subDays(7);

        $base = DB::connection($conn)->table('logbook_system_logs');

        $total24h = (clone $base)->where('created_at', '>=', $since24h)->count();

        $errors24h = (clone $base)
            ->where('created_at', '>=', $since24h)
            ->whereIn('level', ['emergency', 'alert', 'critical', 'error'])
            ->count();

        $warnings24h = (clone $base)
            ->where('created_at', '>=', $since24h)
            ->where('level', 'warning')
            ->count();

        $topLevels7d = (clone $base)
            ->where('created_at', '>=', $since7d)
            ->select('level', DB::raw('COUNT(*) as c'))
            ->groupBy('level')
            ->orderByDesc('c')
            ->limit(5)
            ->get()
            ->map(fn($r) => ['level' => $r->level ?? 'unknown', 'count' => (int) $r->c])
            ->all();

        return [
            'total_24h' => (int) $total24h,
            'errors_24h' => (int) $errors24h,
            'warnings_24h' => (int) $warnings24h,
            'top_levels_7d' => $topLevels7d,
        ];
    }

    protected function auditStats(string $conn): array
    {
        $now = now();
        $since24h = $now->copy()->subHours(24);
        $since7d  = $now->copy()->subDays(7);

        $base = DB::connection($conn)->table('logbook_audit_logs');

        $total24h = (clone $base)->where('created_at', '>=', $since24h)->count();

        $topActions7d = (clone $base)
            ->where('created_at', '>=', $since7d)
            ->select('action', DB::raw('COUNT(*) as c'))
            ->groupBy('action')
            ->orderByDesc('c')
            ->limit(7)
            ->get()
            ->map(fn($r) => ['action' => $r->action ?? 'unknown', 'count' => (int) $r->c])
            ->all();

        $topUsers7d = (clone $base)
            ->where('created_at', '>=', $since7d)
            ->select(
                DB::raw('COALESCE(user_email, user_id, "unknown") as u'),
                DB::raw('COUNT(*) as c')
            )
            ->groupBy('u')
            ->orderByDesc('c')
            ->limit(7)
            ->get()
            ->map(fn($r) => ['user' => (string) $r->u, 'count' => (int) $r->c])
            ->all();

        return [
            'total_24h' => (int) $total24h,
            'top_actions_7d' => $topActions7d,
            'top_users_7d' => $topUsers7d,
        ];
    }
}
