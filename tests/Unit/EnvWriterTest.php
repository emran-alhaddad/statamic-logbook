<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Support\EnvWriter;
use PHPUnit\Framework\TestCase;

/**
 * The onboarding DB-setup writes LOGBOOK_DB_* into the host's .env —
 * a mistake here corrupts a stranger's application config, so the string
 * transform is covered exhaustively.
 */
final class EnvWriterTest extends TestCase
{
    public function test_replaces_an_existing_key_in_place(): void
    {
        $env = "APP_NAME=Statamic\nLOGBOOK_DB_HOST=old-host\nDB_HOST=127.0.0.1\n";

        $out = EnvWriter::applyToContent($env, ['LOGBOOK_DB_HOST' => 'new-host']);

        $this->assertStringContainsString("LOGBOOK_DB_HOST=new-host\n", $out);
        $this->assertStringNotContainsString('old-host', $out);
        // Neighbours untouched.
        $this->assertStringContainsString("APP_NAME=Statamic\n", $out);
        $this->assertStringContainsString("DB_HOST=127.0.0.1\n", $out);
    }

    public function test_appends_a_missing_key_at_the_end(): void
    {
        $out = EnvWriter::applyToContent("APP_NAME=Statamic\n", ['LOGBOOK_DB_PORT' => '3306']);

        $this->assertSame("APP_NAME=Statamic\nLOGBOOK_DB_PORT=3306\n", $out);
    }

    public function test_does_not_touch_keys_that_merely_share_a_prefix(): void
    {
        $env = "LOGBOOK_DB_DATABASE_BACKUP=keep\nLOGBOOK_DB_DATABASE=old\n";

        $out = EnvWriter::applyToContent($env, ['LOGBOOK_DB_DATABASE' => 'new']);

        $this->assertStringContainsString("LOGBOOK_DB_DATABASE_BACKUP=keep\n", $out);
        $this->assertStringContainsString("LOGBOOK_DB_DATABASE=new\n", $out);
    }

    public function test_quotes_values_with_special_characters(): void
    {
        $out = EnvWriter::applyToContent('', [
            'LOGBOOK_DB_PASSWORD' => 'p@ss word#1',
            'LOGBOOK_DB_HOST' => 'plain-host.example.com',
        ]);

        $this->assertStringContainsString('LOGBOOK_DB_PASSWORD="p@ss word#1"', $out);
        // Simple values stay unquoted.
        $this->assertStringContainsString("LOGBOOK_DB_HOST=plain-host.example.com\n", $out);
    }

    public function test_escapes_quotes_and_backslashes_inside_values(): void
    {
        $out = EnvWriter::applyToContent('', ['LOGBOOK_DB_PASSWORD' => 'a"b\\c']);

        $this->assertStringContainsString('LOGBOOK_DB_PASSWORD="a\\"b\\\\c"', $out);
    }

    public function test_empty_values_are_written_as_quoted_empties(): void
    {
        $out = EnvWriter::applyToContent('', ['LOGBOOK_DB_PASSWORD' => '']);

        $this->assertStringContainsString('LOGBOOK_DB_PASSWORD=""', $out);
    }

    public function test_malformed_keys_are_never_written(): void
    {
        $env = "APP_NAME=Statamic\n";

        $out = EnvWriter::applyToContent($env, [
            'lowercase_key' => 'x',
            'HAS SPACE' => 'x',
            'HAS-DASH' => 'x',
            '' => 'x',
        ]);

        $this->assertSame($env, $out);
    }

    public function test_a_nested_variable_reference_line_is_replaced_cleanly(): void
    {
        // The exact shape this project's .env had: "${DB_HOST}" references.
        $env = "LOGBOOK_DB_HOST=\"\${DB_HOST}\"\n";

        $out = EnvWriter::applyToContent($env, ['LOGBOOK_DB_HOST' => 'db.internal']);

        $this->assertSame("LOGBOOK_DB_HOST=db.internal\n", $out);
    }

    public function test_write_returns_false_when_env_is_missing(): void
    {
        // No base_path()/.env in unit-test context → false, never a throw.
        $this->assertFalse(EnvWriter::write(['LOGBOOK_DB_HOST' => 'x']));
    }
}
