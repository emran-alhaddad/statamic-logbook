<?php

namespace EmranAlhaddad\StatamicLogbook\Widgets;

use Statamic\Widgets\Widget;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use EmranAlhaddad\StatamicLogbook\Support\LogbookDashboardData;

class LogbookStatsWidget extends Widget
{
    public static $handle = 'logbook_stats';

    protected static $title = 'Logbook · Overview';

    /**
     * Permission gate. Statamic's widget pipeline doesn't call this
     * automatically on every major we support, so html() also checks
     * and short-circuits — see the marker-div note below.
     */
    public function canSee()
    {
        return auth()->user()?->can('view logbook') ?? false;
    }

    public function html()
    {
        // Server-side permission gate. If the CP user isn't authorised
        // to view logbook data we return a marker element that carries
        // no data. Our shipped stylesheet collapses the enclosing
        // dashboard card via a `:has()` selector so the widget doesn't
        // occupy a visible slot on the grid — see
        // resources/dist/statamic-logbook.css, block
        // "Dashboard widget permission gate".
        if (! $this->canSee()) {
            return '<div class="lb-widget-gated" data-lb-widget-handle="'.static::$handle.'" aria-hidden="true" hidden></div>';
        }

        try {
            $conn = DbConnectionResolver::resolve();
            return view('statamic-logbook::cp.widgets.logbook_cards', LogbookDashboardData::summary($conn))->render();
        } catch (\Throwable $e) {
            return '<div class="card p-4 text-sm text-gray-600">Logbook overview could not be loaded. Check the database connection and migrations.</div>';
        }
    }
}
