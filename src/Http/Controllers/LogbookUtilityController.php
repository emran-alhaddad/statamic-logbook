<?php

namespace EmranAlhaddad\StatamicLogbook\Http\Controllers;

use Illuminate\Support\Facades\DB;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;

use Illuminate\Http\Request;

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

        $logs = $q->orderByDesc('id')->paginate(50)->withQueryString();

        $levels = DB::connection($conn)->table('logbook_system_logs')
            ->select('level')->distinct()->orderBy('level')->pluck('level')->all();

        $channels = DB::connection($conn)->table('logbook_system_logs')
            ->select('channel')->whereNotNull('channel')->distinct()->orderBy('channel')->pluck('channel')->all();

        return view('statamic-logbook::cp.logbook.system', [
            'filters' => $request->all(),
            'logs' => $logs,
            'levels' => $levels,
            'channels' => $channels,
        ]);
    }

    public function audit(Request $request)
    {
        // Stage 5C: هنا بنجيب records + filters + pagination
        return view('statamic-logbook::cp.logbook.audit', [
            'filters' => $request->all(),
        ]);
    }
}
