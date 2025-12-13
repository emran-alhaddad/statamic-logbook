<?php

namespace EmranAlhaddad\StatamicLogbook\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;

class PruneCommand extends Command
{
    protected $signature = 'logbook:prune {--days= : Delete records older than N days} {--dry-run : Show counts only, do not delete}';
    protected $description = 'Prune Statamic Logbook system & audit logs older than retention days';

    public function handle(): int
    {
        $conn = DbConnectionResolver::resolve();

        $days = $this->option('days');
        $days = is_numeric($days)
            ? (int) $days
            : (int) config('logbook.retention_days', 365);

        if ($days <= 0) {
            $this->error('Days must be a positive integer.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $this->info("Statamic Logbook prune");
        $this->line("• DB connection: {$conn}");
        $this->line("• Retention days: {$days}");
        $this->line("• Deleting records older than: {$cutoff}");

        $dryRun = (bool) $this->option('dry-run');

        $systemQ = DB::connection($conn)->table('logbook_system_logs')->where('created_at', '<', $cutoff);
        $auditQ  = DB::connection($conn)->table('logbook_audit_logs')->where('created_at', '<', $cutoff);

        $systemCount = (clone $systemQ)->count();
        $auditCount  = (clone $auditQ)->count();

        $this->line("• System logs to delete: {$systemCount}");
        $this->line("• Audit logs to delete: {$auditCount}");

        if ($dryRun) {
            $this->comment('Dry-run enabled. No deletions performed.');
            return self::SUCCESS;
        }

        // delete in chunks to avoid huge deletes
        $deletedSystem = $this->deleteInChunks($conn, 'logbook_system_logs', $cutoff);
        $deletedAudit  = $this->deleteInChunks($conn, 'logbook_audit_logs', $cutoff);

        $this->info("Done.");
        $this->line("• Deleted system logs: {$deletedSystem}");
        $this->line("• Deleted audit logs: {$deletedAudit}");

        return self::SUCCESS;
    }

    protected function deleteInChunks(string $conn, string $table, $cutoff): int
    {
        $deleted = 0;

        while (true) {
            $ids = DB::connection($conn)->table($table)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(2000)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $deleted += DB::connection($conn)->table($table)->whereIn('id', $ids)->delete();
        }

        return $deleted;
    }
}
