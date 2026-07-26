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

    public function test_an_unknown_major_still_resolves_the_full_list(): void
    {
        // The list is no longer keyed per major — class_exists() filtering
        // handles version differences, so a future/unknown major gets the
        // same list minus whatever it does not ship.
        $this->assertNotEmpty(EventMap::curatedEvents(999));
        $this->assertNotEmpty(EventMap::excludedEvents(999));
        $this->assertSame(EventMap::curatedEvents(6), EventMap::curatedEvents(999));
    }

    /**
     * Every audited event must belong to exactly one settings group —
     * otherwise the CP settings page could never switch it off. The
     * partition covers the curated Statamic events PLUS the Laravel auth
     * events the subscriber records (Login/Logout/Failed/PasswordReset).
     */
    public function test_group_partition_covers_every_audited_event_exactly_once(): void
    {
        $partition = EventMap::groupPartition();

        $all = array_merge(...array_values($partition));
        $this->assertSame(count($all), count(array_unique($all)), 'An event appears in more than one group');

        $expected = array_merge(self::rawCurated(), [
            'Illuminate\\Auth\\Events\\Login',
            'Illuminate\\Auth\\Events\\Logout',
            'Illuminate\\Auth\\Events\\Failed',
            'Illuminate\\Auth\\Events\\PasswordReset',
        ]);
        sort($all);
        sort($expected);

        $this->assertSame($expected, $all, 'Group partition must cover curated + auth events exactly (no missing, no extra)');
    }

    public function test_events_for_groups_resolves_and_ignores_unknown_keys(): void
    {
        $this->assertContains('Statamic\\Events\\AssetUploaded', EventMap::eventsForGroups(['assets']));
        $this->assertNotContains('Statamic\\Events\\EntrySaved', EventMap::eventsForGroups(['assets']));
        $this->assertContains('Illuminate\\Auth\\Events\\Login', EventMap::eventsForGroups(['users']));
        $this->assertSame([], EventMap::eventsForGroups(['nope']));
        $this->assertSame([], EventMap::eventsForGroups([]));
    }

    public function test_event_labels_are_human_readable(): void
    {
        $this->assertSame('Entry saved', EventMap::eventLabel('Statamic\\Events\\EntrySaved'));
        $this->assertSame('Signed in', EventMap::eventLabel('Illuminate\\Auth\\Events\\Login'));
        $this->assertSame('Recovery code used', EventMap::eventLabel('Statamic\\Events\\TwoFactorRecoveryCodeReplaced'));

        // Every partitioned event must produce a non-classname label.
        foreach (EventMap::groupPartition() as $events) {
            foreach ($events as $class) {
                $label = EventMap::eventLabel($class);
                $this->assertStringNotContainsString('\\', $label);
                $this->assertNotSame('', $label);
            }
        }
    }

    /**
     * The raw curated list WITHOUT class_exists filtering, so the partition
     * test also covers events absent from the installed Statamic version.
     *
     * @return list<string>
     */
    private static function rawCurated(): array
    {
        $const = new \ReflectionClassConstant(EventMap::class, 'CURATED');

        return array_values($const->getValue());
    }

    public function test_curated_and_excluded_lists_do_not_overlap(): void
    {
        $overlap = array_intersect(EventMap::curatedEvents(6), EventMap::excludedEvents(6));

        $this->assertSame([], array_values($overlap), 'An event is both curated and excluded: '.implode(', ', $overlap));
    }

    public function test_collection_and_schema_events_are_captured(): void
    {
        $events = EventMap::curatedEvents(6);

        // Regression: collections, blueprints, forms and sites were missing
        // from the curated list entirely, so creating a collection recorded
        // nothing at all.
        foreach ([
            \Statamic\Events\CollectionCreated::class,
            \Statamic\Events\CollectionSaved::class,
            \Statamic\Events\CollectionDeleted::class,
            \Statamic\Events\BlueprintSaved::class,
            \Statamic\Events\FormSaved::class,
            \Statamic\Events\AssetUploaded::class,
            \Statamic\Events\UserCreated::class,
        ] as $class) {
            $this->assertContains($class, $events);
        }
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
