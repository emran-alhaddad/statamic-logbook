<?php

namespace EmranAlhaddad\StatamicLogbook\Support;

class LogSpool
{
    public static function mode(): string
    {
        return (string) config('logbook.ingest.mode', 'sync');
    }

    public static function enabled(): bool
    {
        return self::mode() === 'spool';
    }

    public static function enqueueSystem(array $payload): bool
    {
        return self::enqueue('system', $payload);
    }

    public static function enqueueAudit(array $payload): bool
    {
        return self::enqueue('audit', $payload);
    }

    public static function enqueue(string $type, array $payload): bool
    {
        if (! in_array($type, ['system', 'audit'], true)) {
            return false;
        }

        try {
            self::enforceBackpressure();

            $dir = self::typeDir($type);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                return false;
            }

            $line = json_encode([
                'id' => (string) str_replace('.', '', uniqid('lb_', true)),
                'type' => $type,
                'created_at' => (string) ($payload['created_at'] ?? now()->toDateTimeString()),
                'payload' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (! is_string($line) || $line === '') {
                return false;
            }

            $file = self::targetFile($type);
            $fp = @fopen($file, 'ab');
            if ($fp === false) {
                return false;
            }

            try {
                if (! @flock($fp, LOCK_EX)) {
                    return false;
                }

                $ok = @fwrite($fp, $line.PHP_EOL);
                @flock($fp, LOCK_UN);
                return is_int($ok) && $ok > 0;
            } finally {
                @fclose($fp);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function spoolBasePath(): string
    {
        $default = storage_path('app/logbook/spool');
        return rtrim((string) config('logbook.ingest.spool_path', $default), DIRECTORY_SEPARATOR);
    }

    public static function typeDir(string $type): string
    {
        return self::spoolBasePath().DIRECTORY_SEPARATOR.$type;
    }

    public static function failedDir(string $type): string
    {
        return self::spoolBasePath().DIRECTORY_SEPARATOR.'failed'.DIRECTORY_SEPARATOR.$type;
    }

    private static function targetFile(string $type): string
    {
        return self::typeDir($type).DIRECTORY_SEPARATOR.date('Ymd_H').'.ndjson';
    }

    private static function enforceBackpressure(): void
    {
        $maxMb = max(32, (int) config('logbook.ingest.max_spool_mb', 256));
        $maxBytes = $maxMb * 1024 * 1024;
        $total = self::spoolBytes();
        if ($total < $maxBytes) {
            return;
        }

        $policy = (string) config('logbook.ingest.backpressure', 'drop_oldest');
        if ($policy !== 'drop_oldest') {
            return;
        }

        $files = self::spoolFiles('system');
        $files = array_merge($files, self::spoolFiles('audit'));
        usort($files, fn (array $a, array $b) => $a['mtime'] <=> $b['mtime']);

        foreach ($files as $file) {
            @unlink($file['path']);
            $total = self::spoolBytes();
            if ($total < $maxBytes) {
                break;
            }
        }
    }

    public static function spoolFiles(string $type): array
    {
        $pattern = self::typeDir($type).DIRECTORY_SEPARATOR.'*.ndjson';
        $paths = glob($pattern) ?: [];
        $out = [];
        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }
            $out[] = [
                'path' => $path,
                'mtime' => (int) @filemtime($path),
                'size' => (int) @filesize($path),
            ];
        }

        usort($out, fn (array $a, array $b) => $a['mtime'] <=> $b['mtime']);
        return $out;
    }

    public static function spoolBytes(): int
    {
        $sum = 0;
        foreach (['system', 'audit'] as $type) {
            foreach (self::spoolFiles($type) as $file) {
                $sum += (int) $file['size'];
            }
        }
        return $sum;
    }
}
