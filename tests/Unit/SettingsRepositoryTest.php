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

    // ------------------------------------------------------------------
    // Config overlay — CP-saved settings must win over env defaults.
    // ------------------------------------------------------------------

    public function test_apply_to_config_overrides_toggles_and_retention(): void
    {
        $this->primeCache([
            'configured' => true,
            'system_logs' => false,
            'audit_logs' => true,
            'auth_events' => false,
            'activity_views' => true,
            'retention_days' => 90,
        ]);

        SettingsRepository::applyToConfig();

        $this->assertFalse(config('logbook.system_logs.enabled'));
        $this->assertTrue(config('logbook.audit_logs.enabled'));
        $this->assertFalse(config('logbook.audit_logs.auth_events'));
        $this->assertTrue(config('logbook.activity.enabled'));
        $this->assertSame(90, config('logbook.retention_days'));
    }

    public function test_disabled_groups_become_exclude_list_entries(): void
    {
        $groups = array_fill_keys(array_keys(SettingsRepository::groups()), true);
        $groups['assets'] = false;

        $this->primeCache(['configured' => true, 'groups' => $groups]);

        SettingsRepository::applyToConfig();

        $excluded = config('logbook.audit_logs.exclude_events');
        foreach (EventMap::eventsForGroups(['assets']) as $class) {
            $this->assertContains($class, $excluded, "{$class} should be excluded when assets group is off");
        }
        // An enabled group's events must NOT be excluded.
        $this->assertNotContains('Statamic\\Events\\EntrySaved', $excluded);
    }

    public function test_extra_ignore_fields_merge_into_config(): void
    {
        $this->primeCache(['configured' => true, 'ignore_fields_extra' => ' secret_field , internal_notes ']);

        SettingsRepository::applyToConfig();

        $fields = config('logbook.audit_logs.ignore_fields');
        $this->assertContains('secret_field', $fields);
        $this->assertContains('internal_notes', $fields);
        // Shipped defaults survive the merge.
        $this->assertContains('remember_token', $fields);
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
            'groups' => ['content' => 'on', 'bogus_group' => true],
            'retention_days' => '30',
            'ignore_fields_extra' => str_repeat('x', 5000),
        ]);

        $this->assertTrue($clean['configured']);
        $this->assertFalse($clean['system_logs']);
        $this->assertTrue($clean['audit_logs']);
        $this->assertArrayNotHasKey('unknown_key', $clean);
        $this->assertArrayNotHasKey('bogus_group', $clean['groups']);
        $this->assertTrue($clean['groups']['content']);
        $this->assertSame(30, $clean['retention_days']);
        $this->assertSame(1000, mb_strlen($clean['ignore_fields_extra']));
    }

    public function test_sanitize_rejects_out_of_range_retention(): void
    {
        $this->assertSame(365, SettingsRepository::sanitize(['retention_days' => 0])['retention_days']);
        $this->assertSame(365, SettingsRepository::sanitize(['retention_days' => 99999])['retention_days']);
        $this->assertSame(365, SettingsRepository::sanitize(['retention_days' => 'abc'])['retention_days']);
    }

    public function test_every_group_key_has_ui_metadata_and_vice_versa(): void
    {
        $uiKeys = array_keys(SettingsRepository::groups());
        $partitionKeys = array_keys(EventMap::groupPartition());

        sort($uiKeys);
        sort($partitionKeys);

        $this->assertSame($partitionKeys, $uiKeys, 'Settings UI groups and EventMap partition must list the same keys');
    }

    // ------------------------------------------------------------------
    // Presets — what onboarding installs must always be valid.
    // ------------------------------------------------------------------

    public function test_presets_are_sanitize_stable_and_marked_configured(): void
    {
        foreach (SettingsRepository::presets() as $key => $preset) {
            $clean = SettingsRepository::sanitize($preset['settings']);

            $this->assertSame($clean, $preset['settings'], "Preset {$key} must already be in canonical shape");
            $this->assertTrue($clean['configured'], "Preset {$key} must mark the addon configured");
        }
    }

    public function test_recommended_preset_captures_all_groups_without_views(): void
    {
        $rec = SettingsRepository::presets()['recommended']['settings'];

        $this->assertFalse($rec['activity_views']);
        $this->assertSame([], array_keys(array_filter($rec['groups'], fn ($on) => ! $on)), 'recommended enables every group');

        $everything = SettingsRepository::presets()['everything']['settings'];
        $this->assertTrue($everything['activity_views']);
    }

    /** Inject settings into the in-process cache, bypassing the DB. */
    private function primeCache(array $overrides): void
    {
        $prop = new \ReflectionProperty(SettingsRepository::class, 'cache');
        $prop->setAccessible(true);
        $prop->setValue(null, SettingsRepository::sanitize($overrides));
    }
}
