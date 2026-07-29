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

        // Page-view rows are volume, not evidence — they get their own,
        // usually much shorter, retention window from CP settings.
        $viewDays = (int) config('logbook.activity.retention_days', 30);
        $viewCutoff = now()->subDays(max(1, $viewDays));
        $viewCount = DB::connection($conn)->table('logbook_audit_logs')
            ->where('action', 'like', 'statamic.%.viewed')
            ->where('created_at', '<', $viewCutoff)
            ->count();
        $this->line("• Page-view rows to delete (retention {$viewDays}d): {$viewCount}");

        if ($dryRun) {
            $this->comment('Dry-run enabled. No deletions performed.');
            return self::SUCCESS;
        }

        // delete in chunks to avoid huge deletes
        $deletedSystem = $this->deleteInChunks($conn, 'logbook_system_logs', $cutoff);
        $deletedAudit  = $this->deleteInChunks($conn, 'logbook_audit_logs', $cutoff);
        $deletedViews  = $this->deleteInChunks($conn, 'logbook_audit_logs', $viewCutoff, 'statamic.%.viewed');

        $this->info("Done.");
        $this->line("• Deleted system logs: {$deletedSystem}");
        $this->line("• Deleted audit logs: {$deletedAudit}");
        $this->line("• Deleted page-view rows: {$deletedViews}");

        return self::SUCCESS;
    }

    protected function deleteInChunks(string $conn, string $table, $cutoff, ?string $actionLike = null): int
    {
        $deleted = 0;

        while (true) {
            $ids = DB::connection($conn)->table($table)
                ->where('created_at', '<', $cutoff)
                ->when($actionLike !== null, fn ($q) => $q->where('action', 'like', $actionLike))
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
