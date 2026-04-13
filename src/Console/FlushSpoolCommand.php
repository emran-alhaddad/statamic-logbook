<?php

namespace EmranAlhaddad\StatamicLogbook\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use EmranAlhaddad\StatamicLogbook\Support\LogSpool;

class FlushSpoolCommand extends Command
{
    protected $signature = 'logbook:flush-spool {--type=all : system|audit|all} {--limit=1000 : Max records per file pass} {--dry-run : Do not persist, only report counts}';
    protected $description = 'Flush local Logbook spool files into configured DB tables';

    public function handle(): int
    {
        $type = (string) $this->option('type');
        if (! in_array($type, ['system', 'audit', 'all'], true)) {
            $this->error('Invalid --type. Use system, audit, or all.');
            return self::FAILURE;
        }

        $limit = max(100, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $conn = DbConnectionResolver::resolve();

        $types = $type === 'all' ? ['system', 'audit'] : [$type];
        $queuedFilesBefore = 0;
        $failedFilesBefore = 0;
        foreach ($types as $t) {
            $queuedFilesBefore += count(LogSpool::spoolFiles($t));
            $failedFilesBefore += count(LogSpool::failedFiles($t));
        }
        $queuedBytesBefore = LogSpool::spoolBytes();

        $this->line('Logbook spool flush');
        $this->line('• Mode: '.LogSpool::mode());
        $this->line('• Type: '.$type);
        $this->line('• Limit per file: '.$limit);
        $this->line('• DB connection: '.$conn);
        $this->line('• Queued files (before): '.$queuedFilesBefore);
        $this->line('• Queued bytes (before): '.$this->formatBytes($queuedBytesBefore));
        $this->line('• Failed files (before): '.$failedFilesBefore);

        $totalRead = 0;
        $totalInserted = 0;
        $failedFiles = 0;

        foreach ($types as $t) {
            foreach (LogSpool::spoolFiles($t) as $file) {
                $result = $this->flushFile($conn, $t, $file['path'], $limit, $dryRun);
                $totalRead += $result['read'];
                $totalInserted += $result['inserted'];
                if (! $result['ok']) {
                    $failedFiles++;
                    if (! empty($result['failed_path'])) {
                        $this->warn('• Failed spool file moved to: '.$result['failed_path']);
                    }
                    if (! empty($result['error'])) {
                        $this->error('• Flush error: '.$result['error']);
                    }
                }
            }
        }

        $this->info('Done.');
        $this->line('• Read: '.$totalRead);
        $this->line('• Inserted: '.$totalInserted);
        $this->line('• Flush failures this run: '.$failedFiles);
        $queuedFilesAfter = 0;
        $failedFilesAfter = 0;
        foreach ($types as $t) {
            $queuedFilesAfter += count(LogSpool::spoolFiles($t));
            $failedFilesAfter += count(LogSpool::failedFiles($t));
        }
        $this->line('• Queued files (after): '.$queuedFilesAfter);
        $this->line('• Queued bytes (after): '.$this->formatBytes(LogSpool::spoolBytes()));
        $this->line('• Failed files (after): '.$failedFilesAfter);

        return $failedFiles > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{ok: bool, read: int, inserted: int, failed_path?: string, error?: string}
     */
    private function flushFile(string $conn, string $type, string $path, int $limit, bool $dryRun): array
    {
        $processingPath = $path.'.processing';
        if (! @rename($path, $processingPath)) {
            return ['ok' => false, 'read' => 0, 'inserted' => 0, 'error' => 'Unable to lock spool file for processing'];
        }

        $rows = [];
        $read = 0;
        $inserted = 0;
        $ok = true;

        try {
            $handle = @fopen($processingPath, 'rb');
            if ($handle === false) {
                return ['ok' => false, 'read' => 0, 'inserted' => 0, 'error' => 'Unable to open processing spool file'];
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    if ($read >= $limit) {
                        break;
                    }
                    $read++;
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    $record = json_decode($line, true);
                    if (! is_array($record)) {
                        continue;
                    }
                    $payload = $record['payload'] ?? null;
                    if (! is_array($payload)) {
                        continue;
                    }

                    $rows[] = $type === 'system'
                        ? $this->mapSystemRow($payload)
                        : $this->mapAuditRow($payload);
                }
            } finally {
                @fclose($handle);
            }

            if ($dryRun) {
                @unlink($processingPath);
                return ['ok' => true, 'read' => $read, 'inserted' => count($rows)];
            }

            if (! empty($rows)) {
                $table = $type === 'system' ? 'logbook_system_logs' : 'logbook_audit_logs';
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::connection($conn)->table($table)->insert($chunk);
                    $inserted += count($chunk);
                }
            }

            @unlink($processingPath);
        } catch (\Throwable $e) {
            $ok = false;
            $failedDir = LogSpool::failedDir($type);
            if (! is_dir($failedDir)) {
                @mkdir($failedDir, 0775, true);
            }
            $failedPath = $failedDir.DIRECTORY_SEPARATOR.basename($processingPath).'.'.date('YmdHis').'.failed';
            @rename($processingPath, $failedPath);
            return [
                'ok' => false,
                'read' => $read,
                'inserted' => $inserted,
                'failed_path' => $failedPath,
                'error' => $e->getMessage(),
            ];
        }

        return ['ok' => $ok, 'read' => $read, 'inserted' => $inserted];
    }

    private function mapSystemRow(array $p): array
    {
        return [
            'level' => (string) ($p['level'] ?? 'info'),
            'message' => (string) ($p['message'] ?? ''),
            'context' => $p['context'] ?? null,
            'channel' => $p['channel'] ?? null,
            'request_id' => $p['request_id'] ?? null,
            'user_id' => $p['user_id'] ?? null,
            'ip' => $p['ip'] ?? null,
            'method' => $p['method'] ?? null,
            'url' => $p['url'] ?? null,
            'user_agent' => $p['user_agent'] ?? null,
            'created_at' => $this->normalizeDateTime($p['created_at'] ?? null),
        ];
    }

    private function mapAuditRow(array $p): array
    {
        return [
            'action' => (string) ($p['action'] ?? 'audit.event'),
            'user_id' => $p['user_id'] ?? null,
            'user_email' => $p['user_email'] ?? null,
            'subject_type' => (string) ($p['subject_type'] ?? 'statamic'),
            'subject_id' => $p['subject_id'] ?? null,
            'subject_handle' => $p['subject_handle'] ?? null,
            'subject_title' => $p['subject_title'] ?? null,
            'changes' => $p['changes'] ?? null,
            'meta' => $p['meta'] ?? null,
            'ip' => $p['ip'] ?? null,
            'user_agent' => $p['user_agent'] ?? null,
            'created_at' => $this->normalizeDateTime($p['created_at'] ?? null),
        ];
    }

    private function normalizeDateTime(mixed $value): string
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }
            if (is_string($value) && trim($value) !== '') {
                return now()->parse($value)->format('Y-m-d H:i:s');
            }
        } catch (\Throwable $e) {
            // Fall through to now() fallback.
        }

        return now()->format('Y-m-d H:i:s');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2).' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2).' MB';
        }
        return round($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
