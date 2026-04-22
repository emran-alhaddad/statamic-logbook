<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook;

use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector;
use EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber;
use EmranAlhaddad\StatamicLogbook\Console\FlushSpoolCommand;
use EmranAlhaddad\StatamicLogbook\Console\InstallCommand;
use EmranAlhaddad\StatamicLogbook\Console\PruneCommand;
use EmranAlhaddad\StatamicLogbook\Http\Controllers\LogbookUtilityController;
use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogbookRequestContext;
use EmranAlhaddad\StatamicLogbook\SystemLogs\DbSystemLogHandler;
use EmranAlhaddad\StatamicLogbook\Widgets\LogbookPulseWidget;
use EmranAlhaddad\StatamicLogbook\Widgets\LogbookStatsWidget;
use EmranAlhaddad\StatamicLogbook\Widgets\LogbookTrendsWidget;
use EmranAlhaddad\StatamicLogbook\Widgets\Registry\WidgetRegistryShim;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Monolog\Level;
use Statamic\Facades\Permission;
use Statamic\Facades\Utility;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;
use Statamic\Widgets\Widget;

/**
 * Service provider for the Statamic Logbook addon.
 *
 * Compatibility
 * -------------
 * - Statamic 4, 5, 6 (this branch).
 * - Statamic 3 is supported on the dedicated `1.x` LTS branch.
 *
 * Design notes
 * ------------
 * - The historical eager rebind of `app('statamic.widgets')` has been
 *   replaced by the capability-gated {@see WidgetRegistryShim} which
 *   runs after Statamic's booted callbacks and only acts when the
 *   core binding is missing our handles. This keeps Statamic 6 happy
 *   (it binds `statamic.widgets` natively via
 *   {@see \Statamic\Providers\ExtensionServiceProvider}) while still
 *   covering the Statamic 5 widget-registration quirk that required
 *   the original workaround.
 *
 * - Boot logic lives in {@see bootAddon()} (the Statamic-preferred
 *   hook) so it runs after core has finished priming the extensions
 *   container.
 *
 * - The class never holds per-process "already booted" flags; we rely
 *   on container singletons and `Event::hasListeners(...)` where
 *   idempotency matters.
 */
class LogbookServiceProvider extends AddonServiceProvider
{
    /**
     * Widget classes the parent bootWidgets() will iterate and register.
     *
     * @var list<class-string<Widget>>
     */
    protected $widgets = [
        LogbookStatsWidget::class,
        LogbookTrendsWidget::class,
        LogbookPulseWidget::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__ . '/../config/logbook.php', 'logbook');

        $this->app->singleton(AuditRecorder::class);
        $this->app->singleton(ChangeDetector::class);
    }

    /**
     * Statamic calls this after {@see Statamic::booted()} has fired, at
     * which point the CP extensions container is fully populated. This
     * is the right hook for permissions, CP utilities, widget-registry
     * shimming, and the system-log listener.
     */
    public function bootAddon(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'statamic-logbook');

        $this->publishes([
            __DIR__ . '/../config/logbook.php' => config_path('logbook.php'),
        ], 'logbook-config');

        $this->commands([
            InstallCommand::class,
            PruneCommand::class,
            FlushSpoolCommand::class,
        ]);

        $this->registerCpMiddleware();
        $this->registerAuditSubscriber();
        $this->registerSystemLogs();
        $this->registerAddonScheduler();
        $this->registerPermissions();
        $this->bootCpUtility();
        $this->applyWidgetRegistryShimIfNeeded();
    }

    /**
     * Apply the widget registry shim only when the core binding does
     * not already have the Logbook handles. On Statamic 6 this is
     * expected to be a no-op; on Statamic 5 it covers the historical
     * registration quirk.
     *
     * Wrapped in Statamic::booted so it runs after core's own widget
     * registration has completed.
     */
    protected function applyWidgetRegistryShimIfNeeded(): void
    {
        if (! class_exists(Statamic::class)) {
            return;
        }

        Statamic::booted(function (): void {
            try {
                (new WidgetRegistryShim($this->app, $this->widgets))->applyIfNeeded();
            } catch (\Throwable $e) {
                // Shim is strictly a back-compat safety net. If it fails, the
                // addon must still boot — widgets will use whatever the host
                // already has registered.
            }
        });
    }

    protected function registerAuditSubscriber(): void
    {
        if (! (bool) config('logbook.audit_logs.enabled', true)) {
            return;
        }

        if (! class_exists(Statamic::class)) {
            return;
        }

        (new StatamicAuditSubscriber(
            recorder: $this->app->make(AuditRecorder::class),
            detector: $this->app->make(ChangeDetector::class),
        ))->subscribe();
    }

    protected function registerAddonScheduler(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $ingestMode = (string) config('logbook.ingest.mode', 'sync');
            $enabled = (bool) config('logbook.scheduler.flush_spool.enabled', true);

            if ($ingestMode !== 'spool' || ! $enabled) {
                return;
            }

            $everyMinutes = (int) config('logbook.scheduler.flush_spool.every_minutes', 60);
            if ($everyMinutes < 1 || $everyMinutes > 1440) {
                $everyMinutes = 60;
            }

            $event = $schedule->command('logbook:flush-spool');
            $event->everyMinute()->when(function () use ($everyMinutes): bool {
                $now = now();
                $minuteOfDay = (int) $now->format('G') * 60 + (int) $now->format('i');

                return $minuteOfDay % $everyMinutes === 0;
            });

            if ((bool) config('logbook.scheduler.flush_spool.without_overlapping', true)) {
                $event->withoutOverlapping();
            }
        });
    }

    protected function registerSystemLogs(): void
    {
        if (! (bool) config('logbook.system_logs.enabled', true)) {
            return;
        }

        // Idempotent: if the listener for MessageLogged already includes a
        // closure bearing our marker, skip re-registering. We use a simple
        // attribute-bag flag on the container rather than a static class
        // variable — containers are re-built between test requests, static
        // state is not.
        $flag = 'logbook.system_logs_hooked';
        if ($this->app->bound($flag) && $this->app->make($flag) === true) {
            return;
        }
        $this->app->instance($flag, true);

        $levelName = (string) config('logbook.system_logs.level', 'debug');
        $bubble = (bool) config('logbook.system_logs.bubble', true);

        try {
            $level = Level::fromName($levelName);
        } catch (\Throwable) {
            $level = Level::Debug;
        }

        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($level, $bubble): void {
            if ($this->shouldSkipSystemLogEvent($event)) {
                return;
            }

            $handler = new DbSystemLogHandler(
                level: $level,
                bubble: $bubble,
                channel: $event->channel ?? 'logbook'
            );

            $handler->recordMessage(
                level: (string) $event->level,
                message: (string) $event->message,
                context: is_array($event->context) ? $event->context : []
            );
        });
    }

    protected function shouldSkipSystemLogEvent(MessageLogged $event): bool
    {
        $channel = (string) ($event->channel ?? '');
        $message = (string) $event->message;

        $ignoredChannels = array_map('strtolower', (array) config('logbook.system_logs.ignore_channels', [
            'deprecations',
        ]));
        if ($channel !== '' && in_array(strtolower($channel), $ignoredChannels, true)) {
            return true;
        }

        foreach ((array) config('logbook.system_logs.ignore_message_contains', []) as $needle) {
            if (is_string($needle) && $needle !== '' && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function registerPermissions(): void
    {
        Permission::register('view logbook')->label('View Logbook');
        Permission::register('export logbook')->label('Export Logbook');
    }

    protected function registerCpMiddleware(): void
    {
        if (! class_exists(LogbookRequestContext::class)) {
            return;
        }

        try {
            Route::pushMiddlewareToGroup('statamic.cp', LogbookRequestContext::class);
        } catch (\Throwable) {
            // Middleware group may not exist yet in some test kernels.
        }
    }

    protected function bootCpUtility(): void
    {
        Utility::extend(function (): void {
            Utility::register('logbook')
                ->title('Logbook')
                ->navTitle('Logbook')
                ->description('System logs + user audit logs')
                ->icon($this->svgIcon('logbook'))
                ->action(LogbookUtilityController::class)
                ->routes(function ($router): void {
                    $router->get('/system', [LogbookUtilityController::class, 'system'])
                        ->name('system')
                        ->middleware('can:view logbook');

                    $router->get('/audit', [LogbookUtilityController::class, 'audit'])
                        ->name('audit')
                        ->middleware('can:view logbook');

                    $router->get('/system/export.csv', [LogbookUtilityController::class, 'exportSystemCsv'])
                        ->name('system.export')
                        ->middleware('can:export logbook');

                    $router->get('/audit/export.csv', [LogbookUtilityController::class, 'exportAuditCsv'])
                        ->name('audit.export')
                        ->middleware('can:export logbook');

                    $router->post('/actions/prune', [LogbookUtilityController::class, 'runPrune'])
                        ->name('actions.prune')
                        ->middleware('can:view logbook');

                    $router->post('/actions/flush-spool', [LogbookUtilityController::class, 'runFlushSpool'])
                        ->name('actions.flush-spool')
                        ->middleware('can:view logbook');
                });
        });
    }

    protected function svgIcon(string $name): string
    {
        $path = __DIR__ . '/../resources/svg/' . $name . '.svg';

        return file_exists($path) ? (string) file_get_contents($path) : '';
    }
}
