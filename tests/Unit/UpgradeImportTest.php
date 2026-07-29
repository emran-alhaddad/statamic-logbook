<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Support\SettingsRepository;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Upgrading from a pre-2.1 install must not silently reset configuration.
 * Everything was configured through .env / config/logbook.php back then, so
 * the CP settings row is seeded from it on first run.
 */
final class UpgradeImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SettingsRepository::resetCache();

        $container = Container::getInstance();
        $container->flush();
        Container::setInstance($container);
        $container->instance('config', new Repository(['logbook' => []]));
    }

    protected function tearDown(): void
    {
        SettingsRepository::resetCache();
        Container::setInstance(null);
    }

    /** @param array<string, mixed> $logbook */
    private function withConfig(array $logbook): void
    {
        Container::getInstance()->instance('config', new Repository(['logbook' => $logbook]));
    }

    public function test_import_marks_the_install_configured_so_setup_is_skipped(): void
    {
        $s = SettingsRepository::importFromFileConfig();

        $this->assertTrue($s['configured'], 'An upgraded install must not be sent through first-run setup');
    }

    public function test_stream_switches_and_retention_carry_over(): void
    {
        $this->withConfig([
            'system_logs' => ['enabled' => false],
            'audit_logs' => ['enabled' => true],
            'retention_days' => 90,
        ]);

        $s = SettingsRepository::importFromFileConfig();

        $this->assertFalse($s['system_logs']);
        $this->assertTrue($s['audit_logs']);
        $this->assertSame(90, $s['retention_days']);
    }

    /**
     * The pre-2.1 config had a minimum-severity threshold, not per-level
     * switches. Turning every level on would multiply an upgrader's log
     * volume, so only levels at or above the old threshold are enabled.
     */
    public function test_old_level_threshold_becomes_per_level_switches(): void
    {
        $this->withConfig(['system_logs' => ['level' => 'warning']]);

        $levels = SettingsRepository::importFromFileConfig()['system_levels'];

        foreach (['emergency', 'alert', 'critical', 'error', 'warning'] as $on) {
            $this->assertTrue($levels[$on], "{$on} is at/above the threshold and must stay captured");
        }
        foreach (['notice', 'info', 'debug'] as $off) {
            $this->assertFalse($levels[$off], "{$off} is below the threshold and must not start being captured");
        }
    }

    public function test_default_debug_threshold_keeps_every_level(): void
    {
        $this->withConfig(['system_logs' => ['level' => 'debug']]);

        $levels = SettingsRepository::importFromFileConfig()['system_levels'];

        $this->assertSame([], array_keys(array_filter($levels, fn ($v) => $v === false)));
    }

    public function test_an_unknown_threshold_keeps_every_level(): void
    {
        $this->withConfig(['system_logs' => ['level' => 'nonsense']]);

        $levels = SettingsRepository::importFromFileConfig()['system_levels'];

        $this->assertNotContains(false, $levels);
    }

    public function test_explicit_capture_levels_win_over_the_threshold(): void
    {
        $this->withConfig([
            'system_logs' => ['level' => 'debug', 'capture_levels' => ['error', 'warning']],
        ]);

        $levels = SettingsRepository::importFromFileConfig()['system_levels'];

        $this->assertTrue($levels['error']);
        $this->assertTrue($levels['warning']);
        $this->assertFalse($levels['info']);
        $this->assertFalse($levels['debug']);
    }

    public function test_env_excluded_events_show_as_disabled_in_the_ui(): void
    {
        $this->withConfig([
            'audit_logs' => ['exclude_events' => [
                \Statamic\Events\EntrySaved::class,
                'Statamic\\Events\\NotARealEvent',   // unknown → dropped
                12345,                                // not a string → dropped
            ]],
        ]);

        $disabled = SettingsRepository::importFromFileConfig()['disabled_events'];

        $this->assertSame([\Statamic\Events\EntrySaved::class], $disabled);
    }

    /**
     * Page-view tracking is new and high volume — an upgrade must never turn
     * it on for someone who never asked for it.
     */
    public function test_page_view_tracking_stays_off_after_an_upgrade(): void
    {
        $this->withConfig(['activity' => ['enabled' => true]]);

        $this->assertFalse(SettingsRepository::importFromFileConfig()['activity_views']);
    }

    public function test_import_produces_a_shape_that_survives_sanitize(): void
    {
        $this->withConfig([
            'system_logs' => ['enabled' => true, 'level' => 'error'],
            'audit_logs' => ['enabled' => false],
            'retention_days' => 30,
        ]);

        $imported = SettingsRepository::importFromFileConfig();
        $round = SettingsRepository::sanitize($imported);

        $this->assertSame($imported, $round, 'Imported settings must round-trip through sanitize() unchanged');
    }

    public function test_out_of_range_retention_falls_back_instead_of_breaking(): void
    {
        $this->withConfig(['retention_days' => 999999]);

        $s = SettingsRepository::sanitize(SettingsRepository::importFromFileConfig());

        $this->assertSame(365, $s['retention_days']);
    }
}
