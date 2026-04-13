<?php

namespace EmranAlhaddad\StatamicLogbook;

use Illuminate\Support\Facades\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Log\Events\MessageLogged;
use Monolog\Level;
use Statamic\Facades\Utility;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Widgets\Widget;

use EmranAlhaddad\StatamicLogbook\Console\InstallCommand;
use EmranAlhaddad\StatamicLogbook\Console\PruneCommand;
use EmranAlhaddad\StatamicLogbook\Console\FlushSpoolCommand;
use EmranAlhaddad\StatamicLogbook\Http\Controllers\LogbookUtilityController;
use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogbookRequestContext;
use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector;
use EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber;
use EmranAlhaddad\StatamicLogbook\SystemLogs\DbSystemLogHandler;
use EmranAlhaddad\StatamicLogbook\Widgets\LogbookPulseWidget;
use EmranAlhaddad\StatamicLogbook\Widgets\LogbookStatsWidget;
use EmranAlhaddad\StatamicLogbook\Widgets\LogbookTrendsWidget;

class LogbookServiceProvider extends AddonServiceProvider
{
    /**
     * Required for {@see AddonServiceProvider::bootWidgets()} to call
     * {@see Widget::register()} on each class.
     *
     * @var list<class-string<Widget>>
     */
    protected $widgets = [
        LogbookStatsWidget::class,
        LogbookTrendsWidget::class,
        LogbookPulseWidget::class,
    ];

    protected static bool $booted = false;
    protected static bool $systemLogsHooked = false;

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__ . '/../config/logbook.php', 'logbook');

        $this->app->singleton(AuditRecorder::class);
        $this->app->singleton(ChangeDetector::class);
    }

    public function boot(): void
    {
        parent::boot();
        $this->bootLogbook();
        $this->registerMergedStatamicWidgetsBinding();
    }

    /**
     * Statamic's {@see \Statamic\Widgets\Loader} reads `app('statamic.widgets')`, which is
     * bound to `statamic.extensions[Widget::class]`. Core only registers concrete widget
     * classes; the abstract map must contain handle → class. Rebind lazily so this runs
     * after {@see \Statamic\Statamic::runBootedCallbacks()} (when subclasses are registered).
     */
    protected function registerMergedStatamicWidgetsBinding(): void
    {
        $this->app->bind('statamic.widgets', function ($app) {
            $abstract = Widget::class;

            if (! $app->bound('statamic.extensions')) {
                return collect();
            }

            $extensions = $app['statamic.extensions'];
            if (! $extensions instanceof \Illuminate\Support\Collection) {
                return collect();
            }

            $merged = collect($extensions[$abstract] ?? []);

            foreach ($extensions as $class => $bindings) {
                if (! is_string($class) || ! class_exists($class)) {
                    continue;
                }
                if ($class === $abstract || ! is_subclass_of($class, $abstract)) {
                    continue;
                }
                if (! $bindings instanceof \Illuminate\Support\Collection) {
                    continue;
                }
                $merged = $merged->merge($bindings);
            }

            foreach ([
                LogbookStatsWidget::class,
                LogbookTrendsWidget::class,
                LogbookPulseWidget::class,
            ] as $widgetClass) {
                if ($merged->has($widgetClass::handle())) {
                    continue;
                }
                $widgetClass::register();
                $map = $extensions[$widgetClass] ?? null;
                if ($map instanceof \Illuminate\Support\Collection) {
                    $merged = $merged->merge($map);
                } else {
                    $merged->put($widgetClass::handle(), $widgetClass);
                }
            }

            return $merged;
        });
    }

    protected function bootLogbook(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'statamic-logbook');

        $this->publishes([
            __DIR__ . '/../config/logbook.php' => config_path('logbook.php'),
        ], 'logbook-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PruneCommand::class,
                FlushSpoolCommand::class,
            ]);
        }

        $this->registerCpMiddleware();

        if (config('logbook.audit_logs.enabled', true) && class_exists(\Statamic\Statamic::class)) {
            (new StatamicAuditSubscriber(
                recorder: $this->app->make(AuditRecorder::class),
                detector: $this->app->make(ChangeDetector::class),
            ))->subscribe();
        }

        $this->registerSystemLogs();
        $this->registerPermissions();
        $this->bootCpUtility();
    }

    protected function registerSystemLogs(): void
    {
        if (self::$systemLogsHooked || !config('logbook.system_logs.enabled', true)) {
            return;
        }

        self::$systemLogsHooked = true;

        $levelName = (string) config('logbook.system_logs.level', 'debug');
        $bubble = (bool) config('logbook.system_logs.bubble', true);

        try {
            $level = Level::fromName($levelName);
        } catch (\Throwable $e) {
            $level = Level::Debug;
        }

        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($level, $bubble) {
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
        if (!class_exists(LogbookRequestContext::class)) return;

        try {
            Router::pushMiddlewareToGroup('statamic.cp', LogbookRequestContext::class);
        } catch (\Throwable $e) {
        }
    }

    protected function bootCpUtility(): void
    {
        Utility::extend(function () {
            Utility::register('logbook')
                ->title('Logbook')
                ->navTitle('Logbook')
                ->description('System logs + user audit logs')
                ->icon($this->svgIcon('logbook'))
                ->action(LogbookUtilityController::class)
                ->routes(function ($router) {
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
                });
        });
    }


    protected function svgIcon(string $name): string
    {
        $path = __DIR__ . '/../resources/svg/' . $name . '.svg';
        return file_exists($path) ? file_get_contents($path) : '';
    }
}
