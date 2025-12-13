<?php

namespace EmranAlhaddad\StatamicLogbook;

use Illuminate\Support\ServiceProvider;
use EmranAlhaddad\StatamicLogbook\Console\InstallCommand;
use Illuminate\Support\Facades\Router;
use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogbookRequestContext;
use Statamic\Facades\Utility;
use EmranAlhaddad\StatamicLogbook\Http\Controllers\LogbookUtilityController;

class LogbookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/logbook.php', 'logbook');
        $this->app->singleton(\EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder::class);
        $this->app->singleton(\EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'statamic-logbook');

        $this->publishes([
            __DIR__ . '/../config/logbook.php' => config_path('logbook.php'),
        ], 'logbook-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }

        $this->registerCpMiddleware();
        if (config('logbook.audit_logs.enabled', true) && class_exists(\Statamic\Statamic::class)) {
            $subscriber = new \EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber(
                recorder: $this->app->make(\EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder::class),
                detector: $this->app->make(\EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector::class),
            );

            $subscriber->subscribe();
        }

        $this->bootCpUtility();
    }

    protected function registerCpMiddleware(): void
    {
        // Only if middleware exists
        if (! class_exists(LogbookRequestContext::class)) {
            return;
        }

        // Only register when CP logging is enabled (optional – if you have the flag)
        // if (!config('logbook.system_logs.enabled', true)) return;

        try {
            // Attach to Statamic Control Panel group (CP only)
            Router::pushMiddlewareToGroup('statamic.cp', LogbookRequestContext::class);
        } catch (\Throwable $e) {
            // Fail silently – don't break the app if a project has a different setup
            // (We can add a debug log later if needed)
        }
    }

    protected function bootCpUtility(): void
    {
        Utility::extend(function () {
            Utility::register('logbook')
                ->title('Logbook')
                ->navTitle('Logbook')
                ->description('System logs + user audit logs in one place.')
                ->icon('logbook') // built-in icon name (safe + simple)
                ->action(LogbookUtilityController::class) // __invoke
                ->routes(function ($router) {
                    $router->get('/system', [LogbookUtilityController::class, 'system'])->name('system');
                    $router->get('/audit',  [LogbookUtilityController::class, 'audit'])->name('audit');

                    // Stage 5D لاحقًا:
                    // $router->get('/system/export.csv', ...)->name('system.export');
                    // $router->get('/audit/export.csv', ...)->name('audit.export');
                });
        });
    }
}
