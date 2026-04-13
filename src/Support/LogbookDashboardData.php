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
     * @return array{systemTotal24h: int, systemErrors24h: int, auditTotal24h: int, topAction7d: object|null, errorRatio: float, userActivity: list<array{user_id: string, email: string|null, last_at: Carbon, actions: int}>}
     */
    public static function summary(string $conn): array
    {
        $since24h = now()->subHours(24);
        $since7d = now()->subDays(7);

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

        return [
            'systemTotal24h' => $systemTotal24h,
            'systemErrors24h' => $systemErrors24h,
            'auditTotal24h' => $auditTotal24h,
            'topAction7d' => $topAction7d,
            'errorRatio' => $errorRatio,
            'userActivity' => self::userAuditRollup($conn, 7, 6),
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
     * @return list<array{date: string, label: string, system: int, errors: int, audit: int}>
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

    protected static function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }
}
