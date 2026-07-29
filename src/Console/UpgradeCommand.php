<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Console;

use EmranAlhaddad\StatamicLogbook\Support\SettingsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Upgrade an existing Logbook install to the CP-settings era (2.1+).
 *
 * Existing log rows are never touched — the log table schemas are unchanged.
 * What this does is:
 *
 *   1. create the tables added since the installed version (idempotent),
 *   2. import the .env / config/logbook.php configuration into the CP
 *      settings row, so an upgrade does not silently reset the operator's
 *      choices or push them through first-run database setup.
 *
 * Safe to run repeatedly; it will not overwrite settings you have already
 * saved in the CP unless you pass --force.
 */
class UpgradeCommand extends Command
{
    protected $signature = 'logbook:upgrade {--force : Re-import from .env even if CP settings already exist}';

    protected $description = 'Upgrade Logbook: add new tables and import existing .env config into CP settings';

    public function handle(): int
    {
        $this->info('Statamic Logbook upgrade');

        $hadLogTables = SettingsRepository::hasLogTables();
        $hadSettings = SettingsRepository::hasStoredSettings();

        // 1. Tables. Install is idempotent and reports what it created.
        $this->line('');
        $this->line('Checking tables…');
        Artisan::call('logbook:install');
        foreach (array_filter(explode("\n", trim(Artisan::output()))) as $line) {
            $this->line('  '.trim($line));
        }

        // 2. Settings.
        $this->line('');

        if (! $hadLogTables) {
            $this->line('No existing Logbook tables were found — this is a fresh install.');
            $this->line('Open Utilities → Logbook → Settings to finish setup.');

            return self::SUCCESS;
        }

        if ($hadSettings && ! $this->option('force')) {
            $this->line('CP settings already exist — nothing to import.');
            $this->comment('Use --force to overwrite them from .env / config/logbook.php.');

            return self::SUCCESS;
        }

        SettingsRepository::resetCache();
        $imported = SettingsRepository::importFromFileConfig();

        if (! SettingsRepository::save($imported)) {
            $this->error('Could not write the settings row. Check the Logbook database connection.');

            return self::FAILURE;
        }

        SettingsRepository::resetCache();

        $levelsOn = array_keys(array_filter($imported['system_levels']));

        $this->info('Imported your existing configuration into CP settings:');
        $this->line('  • System logs:      '.($imported['system_logs'] ? 'on' : 'off'));
        $this->line('  • Captured levels:  '.(count($levelsOn) === count(SettingsRepository::LEVELS)
            ? 'all'
            : implode(', ', $levelsOn)));
        $this->line('  • Audit logs:       '.($imported['audit_logs'] ? 'on' : 'off'));
        $this->line('  • Disabled events:  '.(count($imported['disabled_events']) ?: 'none'));
        $this->line('  • Retention:        '.$imported['retention_days'].' days');
        $this->line('  • Page views:       off (new feature — enable it in Settings if you want it)');

        $this->line('');
        $this->info('Done. Your logs and configuration are intact.');
        $this->line('Settings now live in the CP: Utilities → Logbook → Settings.');
        $this->comment('Your LOGBOOK_* env values still act as defaults; CP settings take precedence.');

        return self::SUCCESS;
    }
}
