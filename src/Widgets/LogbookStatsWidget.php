<?php

namespace EmranAlhaddad\StatamicLogbook\Widgets;

use Statamic\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;

class LogbookStatsWidget extends Widget
{
    protected static $handle = 'logbook_stats';
    protected static $title  = 'Logbook Overview';


    public function canSee()
    {
        return auth()->user()?->can('view logbook') ?? false;
    }


    public function html()
    {
        $conn = DbConnectionResolver::resolve();

        $since24h = now()->subHours(24);
        $since7d  = now()->subDays(7);

        // System logs
        $systemTotal24h = DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since24h)
            ->count();

        $systemErrors24h = DB::connection($conn)
            ->table('logbook_system_logs')
            ->where('created_at', '>=', $since24h)
            ->whereIn('level', ['emergency', 'alert', 'critical', 'error'])
            ->count();

        // Audit logs
        $auditTotal24h = DB::connection($conn)
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

        return view('statamic-logbook::cp.widgets.logbook_stats', [
            'systemTotal24h' => $systemTotal24h,
            'systemErrors24h' => $systemErrors24h,
            'auditTotal24h' => $auditTotal24h,
            'topAction7d' => $topAction7d,
        ]);
    }
}
