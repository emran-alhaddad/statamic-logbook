<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Support;

/**
 * Minimal .env editor for the onboarding DB-setup step: replaces or
 * appends LOGBOOK_DB_* keys without disturbing anything else in the file.
 *
 * Values are quoted when they contain anything beyond [A-Za-z0-9_.-],
 * with backslashes and double quotes escaped. The write is atomic
 * (tmp file + rename) so a crash can never leave a half-written .env.
 */
class EnvWriter
{
    /**
     * Pure string transform — replace or append KEY=VALUE lines.
     * Split out so it is unit-testable without touching the filesystem.
     *
     * @param  array<string, string>  $values
     */
    public static function applyToContent(string $content, array $values): string
    {
        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue; // never write a malformed key
            }

            $line = $key.'='.self::quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace($pattern, $line, $content, 1);
            } else {
                $content = rtrim($content, "\n")."\n".$line."\n";
            }
        }

        return $content;
    }

    /**
     * Write the given keys into the app's .env. Returns false (never
     * throws) when the file is missing or not writable.
     *
     * @param  array<string, string>  $values
     */
    public static function write(array $values): bool
    {
        try {
            $path = function_exists('base_path') ? base_path('.env') : null;
            if ($path === null || ! is_file($path) || ! is_writable($path)) {
                return false;
            }

            $content = file_get_contents($path);
            if (! is_string($content)) {
                return false;
            }

            $updated = self::applyToContent($content, $values);

            $tmp = $path.'.logbook-tmp';
            if (file_put_contents($tmp, $updated, LOCK_EX) === false) {
                return false;
            }

            return rename($tmp, $path);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_.\-]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
