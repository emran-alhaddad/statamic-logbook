<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\LogbookServiceProvider;
use EmranAlhaddad\StatamicLogbook\SystemLogs\LogChannelTap;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\TestCase;

/**
 * Issue #3: `ignore_channels` was dead config because
 * `MessageLogged::$channel` does not exist. The provider now attaches a
 * tap to every configured channel and filters on what the tap reports.
 */
final class SystemLogChannelTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository([
            'logbook' => [
                'system_logs' => [
                    'ignore_channels' => ['deprecations'],
                    'ignore_message_contains' => ['Since symfony/http-foundation'],
                    'capture_levels' => [],
                ],
            ],
            'logging' => [
                'default' => 'stack',
                'deprecations' => ['channel' => 'null', 'trace' => false],
                'channels' => [
                    'stack' => ['driver' => 'stack', 'channels' => ['single']],
                    'single' => ['driver' => 'single', 'path' => '/dev/null'],
                ],
            ],
        ]));
        Container::setInstance($this->app);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        Container::setInstance(null);
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $provider = new LogbookServiceProvider($this->app);
        $reflection = new \ReflectionMethod($provider, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($provider, ...$args);
    }

    // ------------------------------------------------------------------
    // Tap wiring
    // ------------------------------------------------------------------

    public function test_every_configured_channel_gets_a_tap_naming_itself(): void
    {
        $this->call('tapLogChannels');

        $this->assertSame(
            [LogChannelTap::class.':stack'],
            config('logging.channels.stack.tap')
        );
        $this->assertSame(
            [LogChannelTap::class.':single'],
            config('logging.channels.single.tap')
        );
    }

    public function test_tapping_preserves_taps_the_host_configured(): void
    {
        config(['logging.channels.single.tap' => ['App\\Logging\\CustomiseFormatter']]);

        $this->call('tapLogChannels');

        $this->assertSame(
            ['App\\Logging\\CustomiseFormatter', LogChannelTap::class.':single'],
            config('logging.channels.single.tap')
        );
    }

    public function test_tapping_twice_does_not_duplicate_the_tap(): void
    {
        $this->call('tapLogChannels');
        $this->call('tapLogChannels');

        $this->assertSame([LogChannelTap::class.':single'], config('logging.channels.single.tap'));
    }

    /**
     * Laravel only materialises this channel the first time a
     * deprecation fires, which is after we boot — so without seeding it
     * the default `ignore_channels => ['deprecations']` stays dead.
     */
    public function test_deprecations_channel_is_materialised_and_tapped(): void
    {
        $this->call('tapLogChannels');

        $this->assertSame(
            [LogChannelTap::class.':deprecations'],
            config('logging.channels.deprecations.tap')
        );
        $this->assertSame('monolog', config('logging.channels.deprecations.driver'));
        $this->assertSame(NullHandler::class, config('logging.channels.deprecations.handler'));
    }

    public function test_a_host_configured_deprecations_channel_is_left_alone(): void
    {
        config(['logging.channels.deprecations' => ['driver' => 'daily', 'path' => '/dev/null']]);

        $this->call('tapLogChannels');

        $this->assertSame('daily', config('logging.channels.deprecations.driver'));
        $this->assertSame([LogChannelTap::class.':deprecations'], config('logging.channels.deprecations.tap'));
    }

    public function test_tapping_survives_missing_logging_config(): void
    {
        $this->app->instance('config', new Repository([]));

        $this->call('tapLogChannels');

        $this->assertTrue(true, 'tapLogChannels must never throw during boot.');
    }

    // ------------------------------------------------------------------
    // Filtering on the channel the tap reports
    // ------------------------------------------------------------------

    public function test_an_ignored_channel_is_skipped(): void
    {
        $this->assertTrue($this->call(
            'shouldSkipSystemLogEvent',
            new MessageLogged('warning', 'X is deprecated', []),
            'deprecations'
        ));
    }

    public function test_ignored_channel_matching_is_case_insensitive(): void
    {
        $this->assertTrue($this->call(
            'shouldSkipSystemLogEvent',
            new MessageLogged('warning', 'X is deprecated', []),
            'Deprecations'
        ));
    }

    public function test_other_channels_are_captured(): void
    {
        $this->assertFalse($this->call(
            'shouldSkipSystemLogEvent',
            new MessageLogged('error', 'Payment failed', []),
            'stack'
        ));
    }

    public function test_an_unknown_channel_is_still_captured(): void
    {
        $this->assertFalse($this->call(
            'shouldSkipSystemLogEvent',
            new MessageLogged('error', 'Payment failed', []),
            ''
        ));
    }

    public function test_message_fragment_filtering_still_applies(): void
    {
        $this->assertTrue($this->call(
            'shouldSkipSystemLogEvent',
            new MessageLogged('warning', 'Since symfony/http-foundation 6.3: something', []),
            'stack'
        ));
    }

    public function test_capture_levels_restrict_what_is_stored(): void
    {
        config(['logbook.system_logs.capture_levels' => ['error', 'critical']]);

        $this->assertTrue($this->call(
            'shouldSkipSystemLogEvent',
            new MessageLogged('info', 'noise', []),
            'stack'
        ));
        $this->assertFalse($this->call(
            'shouldSkipSystemLogEvent',
            new MessageLogged('error', 'boom', []),
            'stack'
        ));
    }
}
