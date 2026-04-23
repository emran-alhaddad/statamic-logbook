<?php

namespace EmranAlhaddad\StatamicLogbook\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared queries for CP dashboard widgets (cards, trends, pulse).
 */
class LogbookDashboardData
{
    /** @var list<string> */
    protected static array $errorLevels = ['emergency', 'alert', 'critical', 'error'];

    /**
     * Dashboard summary — counts, 24h hourly sparkline series, and
     * period-over-period deltas (current 24h vs previous 24h).
     *
     * @return array{
     *     systemTotal24h: int,
     *     systemErrors24h: int,
     *     auditTotal24h: int,
     *     topAction7d: object|null,
     *     errorRatio: float,
     *     userActivity: list<array{user_id: string, email: string|null, last_at: Carbon, actions: int}>,
     *     systemTotal24hPrev: int,
     *     systemErrors24hPrev: int,
     *     auditTotal24hPrev: int,
     *     systemSpark24h: list<int>,
     *     errorSpark24h: list<int>,
     *     auditSpark24h: list<int>,
     *     systemDelta: array{value: int, pct: float|null, direction: string},
     *     errorDelta: array{value: int, pct: float|null, direction: string},
     *     auditDelta: array{value: int, pct: float|null, direction: string},
     * }
     */
    public static function summary(string $conn): array
    {
        $now = now();
        $since24h = $now->copy()->subHours(24);
        $prev48h = $now->copy()->subHours(48);
        $since7d = $now->copy()->subDays(7);

        // ---- current 24h counts ------------------------------------------------
        $systemTotal24h = (int) DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since24h)
            ->count();

        $systemErrors24h = (int) DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since24h)
            ->whereIn('level', self::$errorLevels)
            ->count();

        $auditTotal24h = (int) DB::connection($conn)
            ->table('logbook_audit_logs')
            ->where('created_at', '>=', $since24h)
            ->count();

        // ---- previous-window counts (24h..48h ago) -----------------------------
        $systemTotal24hPrev = (int) DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $prev48h)
            ->where('created_at', '<', $since24h)
            ->count();

        $systemErrors24hPrev = (int) DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $prev48h)
            ->where('created_at', '<', $since24h)
            ->whereIn('level', self::$errorLevels)
            ->count();

        $auditTotal24hPrev = (int) DB::connection($conn)
            ->table('logbook_audit_logs')
            ->where('created_at', '>=', $prev48h)
            ->where('created_at', '<', $since24h)
            ->count();

        // ---- topAction over last 7d -------------------------------------------
        $topAction7d = DB::connection($conn)
            ->table('logbook_audit_logs')
            ->where('created_at', '>=', $since7d)
            ->select('action', DB::raw('COUNT(*) as c'))
            ->groupBy('action')
            ->orderByDesc('c')
            ->first();

        $errorRatio = $systemTotal24h > 0
            ? round(($systemErrors24h / $systemTotal24h) * 100, 1)
            : 0.0;

        // ---- 24h hourly sparkline series --------------------------------------
        $systemSpark24h = self::hourlyBuckets(
            DB::connection($conn)
                ->table('logbook_system_logs')
                ->where('created_at', '>=', $since24h)
                ->selectRaw(self::hourExpr($conn).' as h, COUNT(*) as c')
                ->groupBy('h')
                ->pluck('c', 'h')
                ->all(),
            $since24h,
            24
        );

        $errorSpark24h = self::hourlyBuckets(
            DB::connection($conn)
                ->table('logbook_system_logs')
                ->where('created_at', '>=', $since24h)
                ->whereIn('level', self::$errorLevels)
                ->selectRaw(self::hourExpr($conn).' as h, COUNT(*) as c')
                ->groupBy('h')
                ->pluck('c', 'h')
                ->all(),
            $since24h,
            24
        );

        $auditSpark24h = self::hourlyBuckets(
            DB::connection($conn)
                ->table('logbook_audit_logs')
                ->where('created_at', '>=', $since24h)
                ->selectRaw(self::hourExpr($conn).' as h, COUNT(*) as c')
                ->groupBy('h')
                ->pluck('c', 'h')
                ->all(),
            $since24h,
            24
        );

        // ---- peak hour in the 24h window (highest bucket) ---------------------
        $peakHour24h = null;
        if (! empty($systemSpark24h)) {
            $peakIdx = 0;
            $peakVal = 0;
            foreach ($systemSpark24h as $i => $v) {
                if ($v >= $peakVal) { $peakVal = $v; $peakIdx = $i; }
            }
            $peakSlot = $since24h->copy()->startOfHour()->addHours($peakIdx);
            $peakHour24h = [
                'count' => (int) $peakVal,
                'at'    => $peakSlot,
                'label' => $peakSlot->isoFormat('ddd HH:mm'),
            ];
        }

        return [
            'systemTotal24h' => $systemTotal24h,
            'systemErrors24h' => $systemErrors24h,
            'auditTotal24h' => $auditTotal24h,
            'topAction7d' => $topAction7d,
            'errorRatio' => $errorRatio,
            'userActivity' => self::userAuditRollup($conn, 7, 6),

            'systemTotal24hPrev' => $systemTotal24hPrev,
            'systemErrors24hPrev' => $systemErrors24hPrev,
            'auditTotal24hPrev' => $auditTotal24hPrev,

            'systemSpark24h' => $systemSpark24h,
            'errorSpark24h' => $errorSpark24h,
            'auditSpark24h' => $auditSpark24h,

            'systemDelta' => self::delta($systemTotal24h, $systemTotal24hPrev),
            'errorDelta' => self::delta($systemErrors24h, $systemErrors24hPrev),
            'auditDelta' => self::delta($auditTotal24h, $auditTotal24hPrev),

            // Creative additions (F5): uses existing table data in new ways.
            'lastError'        => self::lastErrorAt($conn),
            'errorFingerprints' => self::errorFingerprints($conn, 24, 5),
            'peakHour24h'      => $peakHour24h,
        ];
    }

    /**
     * Users with audit activity: last seen + action count (from logbook audit DB).
     *
     * @return list<array{user_id: string, email: string|null, last_at: Carbon, actions: int}>
     */
    public static function userAuditRollup(string $conn, int $days = 7, int $limit = 6): array
    {
        $days = max(1, min(90, $days));
        $limit = max(1, min(12, $limit));
        $since = now()->subDays($days);

        $rows = DB::connection($conn)
            ->table('logbook_audit_logs')
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->select([
                'user_id',
                DB::raw('MAX(user_email) as user_email'),
                DB::raw('MAX(created_at) as last_at'),
                DB::raw('COUNT(*) as actions'),
            ])
            ->groupBy('user_id')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'user_id' => (string) $row->user_id,
                'email' => $row->user_email ? (string) $row->user_email : null,
                'last_at' => Carbon::parse($row->last_at),
                'actions' => (int) $row->actions,
            ];
        }

        return $out;
    }

    /**
     * Last N calendar days: per-day counts for stacked / bar visuals.
     *
     * @return list<array{date: string, label: string, system: int, errors: int, audit: int, system_info: int}>
     */
    public static function dailyTrends(string $conn, int $days = 7): array
    {
        $days = max(1, min(14, $days));
        $since = now()->subDays($days - 1)->startOfDay();

        $systemByDay = DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as system_count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('system_count', 'd')
            ->all();

        $errorsByDay = DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since)
            ->whereIn('level', self::$errorLevels)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as error_count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('error_count', 'd')
            ->all();

        $auditByDay = DB::connection($conn)
            ->table('logbook_audit_logs')
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as audit_count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('audit_count', 'd')
            ->all();

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $key = $day->toDateString();

            $system = (int) ($systemByDay[$key] ?? 0);
            $errors = (int) ($errorsByDay[$key] ?? 0);
            $audit = (int) ($auditByDay[$key] ?? 0);

            $out[] = [
                'date' => $key,
                'label' => $day->isoFormat('dd D'),
                'system' => $system,
                'errors' => $errors,
                /** Non-error system lines (errors ⊆ system) — clearer stacked bars */
                'system_info' => max(0, $system - $errors),
                'audit' => $audit,
            ];
        }

        return $out;
    }

    /**
     * Mixed recent rows for “pulse” widget (newest first).
     *
     * @return list<array{type: string, label: string, meta: string, at: Carbon, severity: string}>
     */
    public static function recentPulse(string $conn, int $limit = 12): array
    {
        $limit = max(4, min(40, $limit));
        $take = max((int) ceil($limit / 2), 10);

        $systemRows = DB::connection($conn)
            ->table('logbook_system_logs')
            ->orderByDesc('created_at')
            ->limit($take)
            ->get(['level', 'message', 'channel', 'created_at']);

        $auditRows = DB::connection($conn)
            ->table('logbook_audit_logs')
            ->orderByDesc('created_at')
            ->limit($take)
            ->get(['action', 'subject_title', 'subject_handle', 'subject_type', 'user_email', 'created_at']);

        $items = [];

        foreach ($systemRows as $row) {
            $level = (string) ($row->level ?? 'info');
            $severity = in_array($level, self::$errorLevels, true) ? 'error' : 'info';
            $msg = self::truncate((string) ($row->message ?? ''), 72);
            $items[] = [
                'type' => 'system',
                'label' => $msg,
                'meta' => strtoupper($level).' · '.(string) ($row->channel ?? 'app'),
                'at' => Carbon::parse($row->created_at),
                'severity' => $severity,
            ];
        }

        foreach ($auditRows as $row) {
            $title = (string) ($row->subject_title ?? '');
            $handle = (string) ($row->subject_handle ?? '');
            $action = (string) ($row->action ?? 'audit');
            $label = $title !== ''
                ? self::truncate($title, 72)
                : self::truncate($action.($handle !== '' ? ' · '.$handle : ''), 72);
            $meta = $action;
            if ($handle !== '') {
                $meta .= ' · '.$handle;
            }
            if (! empty($row->user_email)) {
                $meta .= ' · '.$row->user_email;
            }
            $items[] = [
                'type' => 'audit',
                'label' => $label,
                'meta' => $meta,
                'at' => Carbon::parse($row->created_at),
                'severity' => 'audit',
            ];
        }

        usort($items, fn ($a, $b) => $b['at']->timestamp <=> $a['at']->timestamp);

        return array_slice($items, 0, $limit);
    }

    /**
     * Compute a period-over-period delta.
     *
     * @return array{value: int, pct: float|null, direction: string}
     */
    public static function delta(int $current, int $previous): array
    {
        $value = $current - $previous;

        if ($previous === 0) {
            $pct = $current === 0 ? 0.0 : null; // "∞" / undefined when prior was 0 and current isn't
        } else {
            $pct = round((($current - $previous) / $previous) * 100, 1);
        }

        if ($value > 0) {
            $direction = 'up';
        } elseif ($value < 0) {
            $direction = 'down';
        } else {
            $direction = 'flat';
        }

        return ['value' => $value, 'pct' => $pct, 'direction' => $direction];
    }

    /**
     * Fill a fixed-size array of $hours buckets aligned to $from (oldest first),
     * merging counts from a `hourKey => count` map returned by the DB.
     *
     * @param  array<string,int|string>  $counts
     * @return list<int>
     */
    protected static function hourlyBuckets(array $counts, Carbon $from, int $hours): array
    {
        $out = array_fill(0, $hours, 0);
        $start = $from->copy()->startOfHour();

        for ($i = 0; $i < $hours; $i++) {
            $slot = $start->copy()->addHours($i);
            $key = $slot->format('Y-m-d H');
            // Some DB drivers may return a string; normalize.
            $match = null;
            foreach ($counts as $k => $v) {
                // Accept either 'Y-m-d H' (13 chars) or 'Y-m-d H:00:00' prefixed formats.
                if ($k === $key || str_starts_with((string) $k, $key)) {
                    $match = (int) $v;
                    break;
                }
            }
            $out[$i] = (int) ($match ?? 0);
        }

        return $out;
    }

    /**
     * SQL expression that produces a bucket key of the form "YYYY-mm-dd HH"
     * suitable for GROUP BY on the current DB driver. SQLite's `strftime`
     * is used for tests; other drivers use `DATE_FORMAT`.
     */
    protected static function hourExpr(string $conn): string
    {
        $driver = config("database.connections.{$conn}.driver", 'mysql');

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H', created_at)",
            'pgsql', 'postgres' => "to_char(created_at, 'YYYY-MM-DD HH24')",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d %H')",
        };
    }

    protected static function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }

    /**
     * Cluster recent errors by a normalised "fingerprint" and return the
     * top N groups. Useful for spotting repeat offenders — "500 rows
     * of the same NullPointerException" is much easier to act on than
     * 500 individual table rows.
     *
     * Normalisation strategy (cheap & deterministic): collapse digits,
     * UUIDs, hex tokens, quoted literals, and email/url-ish things down
     * to placeholders so two errors that differ only by IDs end up in
     * the same group.
     *
     * @return list<array{
     *   signature: string,   // normalised, human-friendly form
     *   example: string,     // the original message we first saw
     *   count: int,
     *   last_at: Carbon,
     *   level: string,
     * }>
     */
    public static function errorFingerprints(string $conn, int $hours = 24, int $limit = 5, int $scan = 500): array
    {
        $hours = max(1, min(168, $hours));
        $limit = max(1, min(20, $limit));
        $scan  = max(50, min(2000, $scan));
        $since = now()->subHours($hours);

        $rows = DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since)
            ->whereIn('level', self::$errorLevels)
            ->orderByDesc('created_at')
            ->limit($scan)
            ->get(['level', 'message', 'created_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $groups = [];
        foreach ($rows as $row) {
            $raw = (string) ($row->message ?? '');
            if ($raw === '') {
                continue;
            }
            $sig = self::fingerprintMessage($raw);
            if ($sig === '') {
                continue;
            }

            if (! isset($groups[$sig])) {
                $groups[$sig] = [
                    'signature' => self::truncate($sig, 96),
                    'example'   => self::truncate($raw, 160),
                    'count'     => 0,
                    'last_at'   => Carbon::parse($row->created_at),
                    'level'     => (string) $row->level,
                ];
            }

            $groups[$sig]['count']++;
            $at = Carbon::parse($row->created_at);
            if ($at->greaterThan($groups[$sig]['last_at'])) {
                $groups[$sig]['last_at'] = $at;
                $groups[$sig]['example'] = self::truncate($raw, 160);
                $groups[$sig]['level']   = (string) $row->level;
            }
        }

        usort($groups, function ($a, $b) {
            if ($a['count'] === $b['count']) {
                return $b['last_at']->timestamp <=> $a['last_at']->timestamp;
            }
            return $b['count'] <=> $a['count'];
        });

        return array_slice(array_values($groups), 0, $limit);
    }

    /**
     * Timestamp of the most recent ERROR-level system log, and a
     * human-friendly "Xh Ym" string for display. Returns null when
     * the error table has been quiet for the configured window.
     *
     * @return array{at: Carbon, ago: string, minutes: int}|null
     */
    public static function lastErrorAt(string $conn, int $lookbackHours = 72): ?array
    {
        $since = now()->subHours(max(1, $lookbackHours));
        $row = DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since)
            ->whereIn('level', self::$errorLevels)
            ->orderByDesc('created_at')
            ->limit(1)
            ->value('created_at');

        if (! $row) {
            return null;
        }

        $at = Carbon::parse($row);
        // Carbon 3 (Laravel 11+) returns a float here; Carbon 2 returns an int.
        // humaniseMinutes() is typed `int`, so without the cast PHP 8.3+ emits
        // an "Implicit conversion from float to int loses precision" warning
        // every time the widget renders — which, ironically, gets recorded as
        // a warning row in our own logbook.
        $diffMinutes = (int) max(0, $at->diffInMinutes(now()));

        return [
            'at' => $at,
            'ago' => self::humaniseMinutes($diffMinutes),
            'minutes' => $diffMinutes,
        ];
    }

    /**
     * 7-day × 24-hour activity heatmap for system logs. Returned as a
     * row-per-day matrix oldest→newest, each row being an array of 24
     * integer counts (hour 0 through 23). Also returns the max cell
     * value so the view can scale intensity.
     *
     * @return array{matrix: list<list<int>>, labels: list<string>, max: int}
     */
    public static function channelHeatmap(string $conn, int $days = 7): array
    {
        $days = max(1, min(14, $days));
        $since = now()->subDays($days - 1)->startOfDay();

        $expr = self::hourExpr($conn);
        $rows = DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since)
            ->selectRaw($expr.' as h, COUNT(*) as c')
            ->groupBy('h')
            ->pluck('c', 'h')
            ->all();

        $matrix = [];
        $labels = [];
        $max = 0;

        for ($d = 0; $d < $days; $d++) {
            $day = $since->copy()->addDays($d);
            $labels[] = $day->isoFormat('dd D');
            $hours = [];
            for ($h = 0; $h < 24; $h++) {
                $key = $day->copy()->setTime($h, 0)->format('Y-m-d H');
                $match = 0;
                foreach ($rows as $k => $v) {
                    if ($k === $key || str_starts_with((string) $k, $key)) {
                        $match = (int) $v;
                        break;
                    }
                }
                $hours[] = $match;
                if ($match > $max) $max = $match;
            }
            $matrix[] = $hours;
        }

        return ['matrix' => $matrix, 'labels' => $labels, 'max' => $max];
    }

    /**
     * Collapse a raw log line into a normalised fingerprint so messages
     * that differ only in IDs / paths / timestamps cluster together.
     *
     * Deterministic transformations, applied in order:
     *   1. UUIDs → #uuid
     *   2. long hex tokens → #hex
     *   3. IP addresses → #ip
     *   4. emails → #email
     *   5. quoted substrings ("foo" / 'bar') → "…"
     *   6. numbers → #
     *   7. runs of whitespace → single space
     */
    protected static function fingerprintMessage(string $msg): string
    {
        $s = $msg;
        $s = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '#uuid', $s) ?? $s;
        $s = preg_replace('/\b[0-9a-f]{16,}\b/i', '#hex', $s) ?? $s;
        $s = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '#ip', $s) ?? $s;
        $s = preg_replace('/[\w._%+-]+@[\w.-]+\.[A-Za-z]{2,}/', '#email', $s) ?? $s;
        $s = preg_replace('/"[^"]{0,80}"/', '"…"', $s) ?? $s;
        $s = preg_replace("/'[^']{0,80}'/", "'…'", $s) ?? $s;
        $s = preg_replace('/\b\d+\b/', '#', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return trim($s);
    }

    protected static function humaniseMinutes(int $minutes): string
    {
        if ($minutes < 1)   return 'just now';
        if ($minutes < 60)  return $minutes.'m';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h < 24)        return $m > 0 ? $h.'h '.$m.'m' : $h.'h';
        $d = intdiv($h, 24);
        $hr = $h % 24;
        return $hr > 0 ? $d.'d '.$hr.'h' : $d.'d';
    }
}
