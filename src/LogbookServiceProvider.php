<?php

namespace EmranAlhaddad\StatamicLogbook;

use Illuminate\Support\Facades\Router;
use Statamic\Facades\Utility;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

use EmranAlhaddad\StatamicLogbook\Console\InstallCommand;
use EmranAlhaddad\StatamicLogbook\Console\PruneCommand;
use EmranAlhaddad\StatamicLogbook\Http\Controllers\LogbookUtilityController;
use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogbookRequestContext;
use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector;
use EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber;

class LogbookServiceProvider extends AddonServiceProvider
{
    protected static bool $booted = false;

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
    }

    public function bootAddon(): void
    {
        $this->bootLogbook();
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
            ]);
        }

        $this->registerCpMiddleware();

        if (config('logbook.audit_logs.enabled', true) && class_exists(\Statamic\Statamic::class)) {
            (new StatamicAuditSubscriber(
                recorder: $this->app->make(AuditRecorder::class),
                detector: $this->app->make(ChangeDetector::class),
            ))->subscribe();
        }

        $this->registerPermissions();
        $this->bootCpUtility();
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
