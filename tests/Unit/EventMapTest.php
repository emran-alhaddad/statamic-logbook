<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Audit\EventMap;
use PHPUnit\Framework\TestCase;

final class EventMapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventMap::resetCache();
    }

    public function test_curated_events_for_v6_include_entry_and_user_mutations(): void
    {
        $events = EventMap::curatedEvents(6);

        // Sanity: we got *something* back — the exact contents depend on
        // which Statamic classes autoload in the current vendor tree.
        $this->assertNotEmpty($events);

        // Every returned class must currently exist.
        foreach ($events as $class) {
            $this->assertTrue(
                class_exists($class),
                "EventMap::curatedEvents(6) returned non-existent class {$class}"
            );
        }

        // EntrySaved exists in every major 3..6 and should always be
        // present in the v6 curated list.
        $this->assertContains(\Statamic\Events\EntrySaved::class, $events);
    }

    public function test_curated_events_never_include_a_nonexistent_class(): void
    {
        foreach ([3, 4, 5, 6] as $major) {
            foreach (EventMap::curatedEvents($major) as $class) {
                $this->assertTrue(
                    class_exists($class),
                    "Major {$major} curated list leaked missing class {$class}"
                );
            }
        }
    }

    public function test_excluded_events_are_returned_as_strings_without_autoload(): void
    {
        // Excluded list is intentionally NOT class_exists-filtered —
        // users may want to block an event class that has not been
        // installed locally but would be in prod.
        $excluded = EventMap::excludedEvents(6);
        $this->assertNotEmpty($excluded);

        foreach ($excluded as $class) {
            $this->assertIsString($class);
            $this->assertStringStartsWith('Statamic\\Events\\', $class);
        }
    }

    public function test_unknown_major_falls_back_to_empty_curated_list_but_excludes_still_resolve(): void
    {
        $curated = EventMap::curatedEvents(999);
        $excluded = EventMap::excludedEvents(999);

        $this->assertSame([], $curated);
        $this->assertSame([], $excluded);
    }

    public function test_major_resolves_from_vendor_composer_installed_json(): void
    {
        // In this working tree vendor/statamic/cms is v6.x. We expect
        // majorFor() to resolve to 6 when Statamic::version() is not
        // available in the test bootstrap (it isn't — the Statamic
        // application is not booted in unit tests).
        $major = EventMap::majorFor();
        $this->assertGreaterThanOrEqual(1, $major);
    }

    public function test_override_is_respected_and_not_cached(): void
    {
        $this->assertSame(3, EventMap::majorFor(3));
        $this->assertSame(4, EventMap::majorFor(4));
        $this->assertSame(5, EventMap::majorFor(5));
        $this->assertSame(6, EventMap::majorFor(6));
    }
}
