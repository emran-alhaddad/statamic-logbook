<?php

namespace EmranAlhaddad\StatamicLogbook\Widgets;

use Statamic\Widgets\Widget;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use EmranAlhaddad\StatamicLogbook\Support\LogbookDashboardData;

class LogbookStatsWidget extends Widget
{
    public static $handle = 'logbook_stats';

    protected static $title = 'Logbook · Overview';

    public function canSee()
    {
        return auth()->user()?->can('view logbook') ?? false;
    }

    public function html()
    {
        try {
            $conn = DbConnectionResolver::resolve();
            return view('statamic-logbook::cp.widgets.logbook_cards', LogbookDashboardData::summary($conn))->render();
        } catch (\Throwable $e) {
            return '<div class="card p-4 text-sm text-gray-600">Logbook overview could not be loaded. Check the database connection and migrations.</div>';
        }
    }
}
