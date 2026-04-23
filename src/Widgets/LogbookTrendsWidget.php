<?php

namespace EmranAlhaddad\StatamicLogbook\Widgets;

use Statamic\Widgets\Widget;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use EmranAlhaddad\StatamicLogbook\Support\LogbookDashboardData;

class LogbookTrendsWidget extends Widget
{
    public static $handle = 'logbook_trends';

    protected static $title = 'Logbook · 7-day volume';

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
            $days = max(1, min(14, (int) $this->config('days', 7)));
            $bars = LogbookDashboardData::dailyTrends($conn, $days);
            $heatmap = LogbookDashboardData::channelHeatmap($conn, $days);

            return view('statamic-logbook::cp.widgets.logbook_trends', [
                'bars' => $bars,
                'heatmap' => $heatmap,
            ])->render();
        } catch (\Throwable $e) {
            return $this->errorHtml();
        }
    }

    protected function errorHtml(): string
    {
        return '<div class="card p-4 text-sm text-gray-600">Logbook trends could not be loaded.</div>';
    }
}
