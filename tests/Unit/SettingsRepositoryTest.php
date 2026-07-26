<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Audit\EventMap;
use EmranAlhaddad\StatamicLogbook\Support\SettingsRepository;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * The settings layer must NEVER throw — it runs during provider boot on
 * every request of every install, including installs whose logbook DB is
 * missing, unreachable, or not yet migrated.
 */
final class SettingsRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        SettingsRepository::resetCache();

        // Minimal Application: config helpers exist, database does NOT —
        // the exact shape of a fresh composer install before logbook:install.
        $app = new \Illuminate\Foundation\Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([
            'logbook' => require dirname(__DIR__, 2).'/config/logbook.php',
        ]));
        Container::setInstance($app);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        SettingsRepository::resetCache();
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        Container::setInstance(null);
    }

    // ------------------------------------------------------------------
    // Failure-proof reads — a broken DB must never take the host down.
    // ------------------------------------------------------------------

    public function test_current_returns_defaults_when_no_database_is_available(): void
    {
        $settings = SettingsRepository::current();

        $this->assertSame(SettingsRepository::sanitize([]), $settings);
        $this->assertFalse($settings['configured']);
    }

    public function test_is_configured_and_is_installed_are_false_without_a_database(): void
    {
        $this->assertFalse(SettingsRepository::isConfigured());
        $this->assertFalse(SettingsRepository::isInstalled());
    }

    public function test_save_returns_false_instead_of_throwing_without_a_database(): void
    {
        $this->assertFalse(SettingsRepository::save(['configured' => true]));
    }

    public function test_apply_to_config_is_a_noop_when_unconfigured(): void
    {
        $before = config('logbook');

        SettingsRepository::applyToConfig();

        $this->assertSame($before, config('logbook'));
    }

    public function test_streams_fall_back_to_file_config_when_unconfigured(): void
    {
        config(['logbook.system_logs.enabled' => false, 'logbook.audit_logs.enabled' => true]);

        $this->assertSame(['system' => false, 'audit' => true], SettingsRepository::streams());
    }

    // ------------------------------------------------------------------
    // Config overlay — CP-saved settings must win over env defaults.
    // ------------------------------------------------------------------

    public function test_apply_to_config_overrides_toggles_levels_and_retention(): void
    {
        $this->primeCache([
            'configured' => true,
            'system_logs' => false,
            'system_levels' => ['error' => true, 'warning' => true] + array_fill_keys(SettingsRepository::LEVELS, false),
            'audit_logs' => true,
            'activity_views' => true,
            'retention_days' => 90,
        ]);

        SettingsRepository::applyToConfig();

        $this->assertFalse(config('logbook.system_logs.enabled'));
        $this->assertTrue(config('logbook.audit_logs.enabled'));
        $this->assertTrue(config('logbook.activity.enabled'));
        $this->assertSame(90, config('logbook.retention_days'));
        $this->assertSame(['error', 'warning'], array_values(config('logbook.system_logs.capture_levels')));
    }

    public function test_streams_reflect_saved_settings_when_configured(): void
    {
        $this->primeCache(['configured' => true, 'system_logs' => false, 'audit_logs' => true]);

        $this->assertSame(['system' => false, 'audit' => true], SettingsRepository::streams());
    }

    public function test_disabled_events_become_exclude_list_entries(): void
    {
        $this->primeCache([
            'configured' => true,
            'disabled_events' => [
                'Statamic\\Events\\AssetUploaded',
                'Illuminate\\Auth\\Events\\Login',
            ],
        ]);

        SettingsRepository::applyToConfig();

        $excluded = config('logbook.audit_logs.exclude_events');
        $this->assertContains('Statamic\\Events\\AssetUploaded', $excluded);
        $this->assertContains('Illuminate\\Auth\\Events\\Login', $excluded);
        $this->assertNotContains('Statamic\\Events\\EntrySaved', $excluded);
    }

    public function test_extra_ignore_fields_and_channels_merge_into_config(): void
    {
        $this->primeCache([
            'configured' => true,
            'ignore_fields_extra' => ' secret_field , internal_notes ',
            'ignore_channels_extra' => 'queries',
        ]);

        SettingsRepository::applyToConfig();

        $fields = config('logbook.audit_logs.ignore_fields');
        $this->assertContains('secret_field', $fields);
        $this->assertContains('internal_notes', $fields);
        $this->assertContains('remember_token', $fields, 'shipped defaults survive the merge');

        $this->assertContains('queries', config('logbook.system_logs.ignore_channels'));
        $this->assertContains('deprecations', config('logbook.system_logs.ignore_channels'));
    }

    // ------------------------------------------------------------------
    // Sanitize — arbitrary input (stored JSON or form POST) must coerce
    // to the exact settings shape.
    // ------------------------------------------------------------------

    public function test_sanitize_drops_unknown_keys_and_coerces_types(): void
    {
        $clean = SettingsRepository::sanitize([
            'configured' => 'on',            // HTML checkbox
            'system_logs' => '0',
            'audit_logs' => 'true',
            'unknown_key' => 'whatever',
            'system_levels' => ['error' => 'on', 'bogus_level' => true],
            'retention_days' => '30',
            'ignore_fields_extra' => str_repeat('x', 5000),
        ]);

        $this->assertTrue($clean['configured']);
        $this->assertFalse($clean['system_logs']);
        $this->assertTrue($clean['audit_logs']);
        $this->assertArrayNotHasKey('unknown_key', $clean);
        $this->assertArrayNotHasKey('bogus_level', $clean['system_levels']);
        $this->assertTrue($clean['system_levels']['error']);
        $this->assertSame(30, $clean['retention_days']);
        $this->assertSame(1000, mb_strlen($clean['ignore_fields_extra']));
    }

    public function test_sanitize_keeps_only_known_event_classes_in_disabled_events(): void
    {
        $clean = SettingsRepository::sanitize([
            'disabled_events' => [
                'Statamic\\Events\\EntrySaved',      // known
                'Illuminate\\Auth\\Events\\Logout',  // known (auth pseudo-event)
                'Evil\\Injected\\Class',             // unknown → dropped
                12345,                                // not a string → dropped
            ],
        ]);

        $this->assertSame(
            ['Statamic\\Events\\EntrySaved', 'Illuminate\\Auth\\Events\\Logout'],
            $clean['disabled_events']
        );
    }

    public function test_sanitize_rejects_out_of_range_retention(): void
    {
        $this->assertSame(365, SettingsRepository::sanitize(['retention_days' => 0])['retention_days']);
        $this->assertSame(365, SettingsRepository::sanitize(['retention_days' => 99999])['retention_days']);
        $this->assertSame(365, SettingsRepository::sanitize(['retention_days' => 'abc'])['retention_days']);
    }

    public function test_group_labels_match_the_event_partition_exactly(): void
    {
        $uiKeys = array_keys(SettingsRepository::groupLabels());
        $partitionKeys = array_keys(EventMap::groupPartition());

        sort($uiKeys);
        sort($partitionKeys);

        $this->assertSame($partitionKeys, $uiKeys, 'Settings UI groups and EventMap partition must list the same keys');
    }

    public function test_defaults_enable_everything_except_page_views(): void
    {
        $d = SettingsRepository::defaults();

        $this->assertFalse($d['configured']);
        $this->assertTrue($d['system_logs']);
        $this->assertTrue($d['audit_logs']);
        $this->assertFalse($d['activity_views']);
        $this->assertSame([], $d['disabled_events']);
        $this->assertSame([], array_keys(array_filter($d['system_levels'], fn ($on) => ! $on)), 'all levels on by default');
    }

    /** Inject settings into the in-process cache, bypassing the DB. */
    private function primeCache(array $overrides): void
    {
        $prop = new \ReflectionProperty(SettingsRepository::class, 'cache');
        $prop->setAccessible(true);
        $prop->setValue(null, SettingsRepository::sanitize($overrides));
    }
}
