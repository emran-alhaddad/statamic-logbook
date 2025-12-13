<?php

namespace EmranAlhaddad\StatamicLogbook;

use Illuminate\Support\ServiceProvider;
use EmranAlhaddad\StatamicLogbook\Console\InstallCommand;


class LogbookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/logbook.php', 'logbook');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/logbook.php' => config_path('logbook.php'),
        ], 'logbook-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
