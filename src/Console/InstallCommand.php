<?php

namespace EmranAlhaddad\StatamicLogbook\Console;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;

class InstallCommand extends Command
{
    protected $signature = 'logbook:install';
    protected $description = 'Install Statamic Logbook database tables';

    public function handle(): int
    {
        $connection = DbConnectionResolver::resolve();

        $this->info("Statamic Logbook using DB connection: {$connection}");

        // 1) Ensure database exists (create if missing)
        $this->ensureDatabaseExists($connection);

        // 2) Create tables (idempotent)
        $this->createSystemLogsTable($connection);
        $this->createAuditLogsTable($connection);

        $this->info('✅ Statamic Logbook installation completed successfully.');
        return self::SUCCESS;
    }

    /**
     * Ensures the configured database exists.
     * Uses the database name AS-IS (no normalization).
     * Only supported for MySQL/MariaDB drivers.
     */
    protected function ensureDatabaseExists(string $connection): void
    {
        $cfg = config("database.connections.{$connection}");
        $driver = $cfg['driver'] ?? null;

        // Only attempt auto-create for mysql (covers MariaDB too)
        if ($driver !== 'mysql') {
            return;
        }

        $dbName = $cfg['database'] ?? null;
        if (! $dbName) {
            $this->error('❌ LOGBOOK_DB_DATABASE is not set.');
            $this->line('Set LOGBOOK_DB_DATABASE in your .env then re-run: php artisan logbook:install');
            exit(self::FAILURE);
        }

        // If connection works, DB exists and we are done.
        try {
            DB::connection($connection)->select('select 1');
            return;
        } catch (QueryException $e) {
            // Only handle "Unknown database" case, otherwise rethrow.
            if (stripos($e->getMessage(), 'Unknown database') === false) {
                throw $e;
            }
        }

        $this->warn("• Database [{$dbName}] does not exist. Creating...");

        // Connect to server without selecting the target DB.
        $serverConnection = $connection . '_server';
        $serverCfg = $cfg;
        $serverCfg['database'] = 'information_schema';

        config(["database.connections.{$serverConnection}" => $serverCfg]);

        try {
            // Create DB using the name AS-IS (wrapped in backticks).
            DB::connection($serverConnection)->statement(
                "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            $this->info("• Database [{$dbName}] created");

            // IMPORTANT: purge the original connection so it reconnects to the newly created DB
            DB::purge($connection);
        } catch (\Throwable $ex) {
            $this->error('❌ Unable to create database.');
            $this->line('Your DB user likely does not have CREATE DATABASE permission.');
            $this->line("Create the database manually: `{$dbName}` then re-run: php artisan logbook:install");
            exit(self::FAILURE);
        }
    }

    protected function createSystemLogsTable(string $connection): void
    {
        $table = 'logbook_system_logs';

        if ($this->safeHasTable($connection, $table)) {
            $this->line("• {$table} already exists");
            return;
        }

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('level', 20)->index();
            $table->text('message');

            $table->json('context')->nullable();

            $table->string('channel', 50)->nullable()->index();
            $table->string('request_id', 64)->nullable()->index();

            $table->string('user_id', 36)->nullable()->index();

            $table->string('ip', 45)->nullable();
            $table->string('method', 10)->nullable();
            $table->text('url')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent()->index();
        });

        $this->info("• created {$table}");
    }

    protected function createAuditLogsTable(string $connection): void
    {
        $table = 'logbook_audit_logs';

        if ($this->safeHasTable($connection, $table)) {
            $this->line("• {$table} already exists");
            return;
        }

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('action', 100)->index();

            $table->string('user_id', 36)->nullable()->index();
            $table->string('user_email', 191)->nullable()->index();

            $table->string('subject_type', 50)->index();
            $table->string('subject_id', 191)->nullable()->index();
            $table->string('subject_handle', 191)->nullable()->index();
            $table->string('subject_title', 191)->nullable();

            $table->json('changes')->nullable();
            $table->json('meta')->nullable();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent()->index();
        });

        $this->info("• created {$table}");
    }

    /**
     * hasTable() will throw if DB doesn't exist.
     * We handle that safely (after ensureDatabaseExists, this is just extra safety).
     */
    protected function safeHasTable(string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (QueryException $e) {
            // If something still points to missing DB, show a clearer message.
            if (stripos($e->getMessage(), 'Unknown database') !== false) {
                $cfg = config("database.connections.{$connection}");
                $dbName = $cfg['database'] ?? '(unknown)';
                $this->error("❌ Connection still points to missing database: {$dbName}");
                $this->line('Run: php artisan config:clear then try again.');
                exit(self::FAILURE);
            }
            throw $e;
        }
    }
}
