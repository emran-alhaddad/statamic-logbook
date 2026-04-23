<?php

namespace EmranAlhaddad\StatamicLogbook\Widgets;

use Statamic\Widgets\Widget;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use EmranAlhaddad\StatamicLogbook\Support\LogbookDashboardData;

class LogbookPulseWidget extends Widget
{
    public static $handle = 'logbook_pulse';

    protected static $title = 'Logbook · Live feed';

    /**
     * Permission gate — see LogbookStatsWidget for rationale.
     */
    public function canSee()
    {
        return auth()->user()?->can('view logbook') ?? false;
    }

    public function html()
    {
        // Permission gate. See LogbookStatsWidget::html() for the
        // details on why this is inside html() and how the CSS hides
        // the enclosing dashboard card via :has(.lb-widget-gated).
        if (! $this->canSee()) {
            return '<div class="lb-widget-gated" data-lb-widget-handle="'.static::$handle.'" aria-hidden="true" hidden></div>';
        }

        try {
            $conn = DbConnectionResolver::resolve();
            $limit = max(4, min(40, (int) $this->config('limit', 12)));
            $items = LogbookDashboardData::recentPulse($conn, $limit);

            return view('statamic-logbook::cp.widgets.logbook_pulse', [
                'items' => $items,
                'pulseId' => 'lb_pulse_'.preg_replace('/\W/', '', uniqid('', true)),
            ])->render();
        } catch (\Throwable $e) {
            return '<div class="card p-4 text-sm text-gray-600">Logbook feed could not be loaded.</div>';
        }
    }
}
