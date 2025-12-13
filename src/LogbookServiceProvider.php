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

use EmranAlhaddad\StatamicLogbook\Widgets\LogbookStatsWidget;

class LogbookServiceProvider extends AddonServiceProvider
{
    /**
     * Register Statamic dashboard widgets (works across Statamic versions).
     */
    protected $widgets = [
        LogbookStatsWidget::class,
    ];

    public function register(): void
    {
        parent::register();

        // Package config
        $this->mergeConfigFrom(__DIR__ . '/../config/logbook.php', 'logbook');

        // NOTE:
        // Do NOT merge into statamic.cp here. Some Statamic versions have different merging expectations,
        // and we already register our widget via $widgets.
        // If you still want auto-add widgets, do it manually in the project config/statamic/cp.php.

        // Singletons
        $this->app->singleton(AuditRecorder::class);
        $this->app->singleton(ChangeDetector::class);
    }

    /**
     * Statamic add-on boot hook.
     */
    public function bootAddon(): void
    {
        // Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'statamic-logbook');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/logbook.php' => config_path('logbook.php'),
        ], 'logbook-config');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PruneCommand::class,
            ]);
        }

        // CP middleware (CP-only request context)
        $this->registerCpMiddleware();

        // Audit subscriber
        if (config('logbook.audit_logs.enabled', true) && class_exists(\Statamic\Statamic::class)) {
            $subscriber = new StatamicAuditSubscriber(
                recorder: $this->app->make(AuditRecorder::class),
                detector: $this->app->make(ChangeDetector::class),
            );

            $subscriber->subscribe();
        }

        // Permissions
        $this->registerPermissions();

        // CP Utility pages
        $this->bootCpUtility();
    }

    protected function registerPermissions(): void
    {
        Permission::register('view logbook')
            ->label('View Logbook (System + Audit)');

        Permission::register('export logbook')
            ->label('Export Logbook (CSV)');
    }

    protected function registerCpMiddleware(): void
    {
        if (!class_exists(LogbookRequestContext::class)) {
            return;
        }

        try {
            Router::pushMiddlewareToGroup('statamic.cp', LogbookRequestContext::class);
        } catch (\Throwable $e) {
            // Fail silently
        }
    }

    protected function bootCpUtility(): void
    {
        Utility::extend(function () {
            Utility::register('logbook')
                ->title('Logbook')
                ->navTitle('Logbook')
                ->description('System logs + user audit logs in one place.')
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

        if (!file_exists($path)) {
            return '';
        }

        return file_get_contents($path) ?: '';
    }
}
