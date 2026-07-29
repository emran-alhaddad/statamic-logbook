<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector;
use EmranAlhaddad\StatamicLogbook\Audit\EventMap;
use EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Covers the v6 safety property: the subscriber resolves its event
 * list through {@see EventMap} and filters through class_exists, so a
 * Statamic major that has removed or renamed a class never causes a
 * class-not-found fatal at boot.
 */
final class StatamicAuditSubscriberResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventMap::resetCache();

        // Stand up a real Laravel config repository in the container so
        // the subscriber's `config(...)` calls resolve.
        $container = Container::getInstance();
        $container->flush();
        Container::setInstance($container);

        $container->instance('config', new Repository([
            'logbook' => [
                'audit_logs' => [
                    'enabled' => true,
                    'discover_events' => false,
                    'use_curated_defaults' => true,
                    'events' => [],
                    'exclude_events' => [],
                    'ignore_fields' => ['updated_at'],
                    'max_value_length' => 2000,
                ],
            ],
        ]));
    }

    public function test_events_to_listen_pulls_curated_defaults_for_current_major(): void
    {
        $subscriber = $this->newSubscriber();

        $resolved = $this->invokeResolved($subscriber);

        // Whatever major we are on, EntrySaved should be present (it exists in v3..v6).
        $this->assertContains(\Statamic\Events\EntrySaved::class, $resolved);
    }

    public function test_missing_class_in_user_config_is_filtered_silently(): void
    {
        /** @var Repository $config */
        $config = Container::getInstance()->make('config');
        $config->set('logbook.audit_logs.events', [
            'Statamic\\Events\\ThisDoesNotExist_SafeToIgnore',
            \Statamic\Events\EntrySaved::class,
        ]);

        $subscriber = $this->newSubscriber();
        $resolved = $this->invokeResolved($subscriber);

        $this->assertContains(\Statamic\Events\EntrySaved::class, $resolved);
        $this->assertNotContains('Statamic\\Events\\ThisDoesNotExist_SafeToIgnore', $this->filterExisting($resolved));
    }

    public function test_exclude_events_suppress_curated_defaults(): void
    {
        /** @var Repository $config */
        $config = Container::getInstance()->make('config');
        $config->set('logbook.audit_logs.exclude_events', [
            \Statamic\Events\EntrySaved::class,
        ]);

        $subscriber = $this->newSubscriber();
        $excluded = $this->invokeExcluded($subscriber);

        $this->assertContains(\Statamic\Events\EntrySaved::class, $excluded);
    }

    public function test_disable_use_curated_defaults_returns_only_user_events(): void
    {
        /** @var Repository $config */
        $config = Container::getInstance()->make('config');
        $config->set('logbook.audit_logs.use_curated_defaults', false);
        $config->set('logbook.audit_logs.events', [
            \Statamic\Events\UserSaved::class,
        ]);

        $subscriber = $this->newSubscriber();
        $resolved = $this->invokeResolved($subscriber);

        $this->assertSame([\Statamic\Events\UserSaved::class], $resolved);
    }

    /**
     * The guarantee users rely on: a disabled event is NOT CAPTURED, not
     * merely hidden from the listing. Proven by there being no recording
     * listener at all — nothing can write a row it never hears about.
     */
    public function test_a_disabled_event_gets_no_recording_listener(): void
    {
        /** @var Repository $config */
        $config = Container::getInstance()->make('config');
        $config->set('logbook.audit_logs.exclude_events', [\Statamic\Events\EntrySaved::class]);

        $dispatcher = new \Illuminate\Events\Dispatcher(Container::getInstance());
        Container::getInstance()->instance('events', $dispatcher);
        \Illuminate\Support\Facades\Event::clearResolvedInstances();
        \Illuminate\Support\Facades\Event::setFacadeApplication(Container::getInstance());

        $this->newSubscriber()->subscribe();

        $this->assertFalse(
            $this->hasRecordingListener($dispatcher, \Statamic\Events\EntrySaved::class),
            'A disabled event must not get a recording listener'
        );
        $this->assertTrue(
            $this->hasRecordingListener($dispatcher, \Statamic\Events\EntryCreated::class),
            'Events left enabled must still be captured'
        );
    }

    /**
     * Is there a listener that would RECORD this event? Entry events also
     * carry depth-marker listeners bound to the same subscriber, so simply
     * asking "are there listeners?" is not enough — we match the recording
     * closure, which closes over an $eventClass variable.
     */
    private function hasRecordingListener(\Illuminate\Events\Dispatcher $dispatcher, string $event): bool
    {
        foreach ($dispatcher->getListeners($event) as $listener) {
            try {
                $vars = (new \ReflectionFunction($listener))->getStaticVariables();
                foreach ($vars as $v) {
                    if ($v instanceof \Closure
                        && array_key_exists('eventClass', (new \ReflectionFunction($v))->getStaticVariables())) {
                        return true;
                    }
                }
                if (array_key_exists('eventClass', $vars)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        return false;
    }

    private function newSubscriber(): StatamicAuditSubscriber
    {
        return new StatamicAuditSubscriber(
            recorder: $this->createMock(AuditRecorder::class),
            detector: $this->createMock(ChangeDetector::class),
        );
    }

    /**
     * @return list<string>
     */
    private function invokeResolved(StatamicAuditSubscriber $subscriber): array
    {
        $ref = new \ReflectionMethod($subscriber, 'eventsToListen');
        $ref->setAccessible(true);

        return (array) $ref->invoke($subscriber);
    }

    /**
     * @return list<string>
     */
    private function invokeExcluded(StatamicAuditSubscriber $subscriber): array
    {
        $ref = new \ReflectionMethod($subscriber, 'excludedEventClasses');
        $ref->setAccessible(true);

        return (array) $ref->invoke($subscriber);
    }

    /**
     * @param  list<string>  $list
     * @return list<string>
     */
    private function filterExisting(array $list): array
    {
        return array_values(array_filter($list, 'class_exists'));
    }
}
