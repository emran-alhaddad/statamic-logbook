<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\SystemLogs\LogChannelTap;
use EmranAlhaddad\StatamicLogbook\SystemLogs\StripChannelMarkerProcessor;
use Illuminate\Events\Dispatcher;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Log\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Regression cover for issue #3: `MessageLogged` has no `channel`
 * property, so the channel has to ride along in the Laravel logger's
 * context — and must be stripped again before any handler writes it.
 */
final class LogChannelTapTest extends TestCase
{
    private function logger(string $channel, TestHandler $handler): Logger
    {
        $logger = new Logger(
            new Monolog('whatever-monolog-calls-itself', [$handler]),
            new Dispatcher()
        );

        (new LogChannelTap())->__invoke($logger, $channel);

        return $logger;
    }

    public function test_tapped_channel_name_reaches_the_dispatched_event(): void
    {
        $handler = new TestHandler();
        $logger = $this->logger('deprecations', $handler);

        $captured = null;
        $logger->listen(function (MessageLogged $event) use (&$captured): void {
            $captured = $event;
        });

        $logger->warning('Something is deprecated');

        $this->assertInstanceOf(MessageLogged::class, $captured);
        [$channel, $context] = LogChannelTap::extract($captured->context);
        $this->assertSame('deprecations', $channel);
        $this->assertSame([], $context, 'The marker must not survive into stored context.');
    }

    public function test_marker_never_reaches_a_handler(): void
    {
        $handler = new TestHandler();
        $this->logger('stack', $handler)->info('hello', ['order_id' => 7]);

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(['order_id' => 7], $records[0]->context);
    }

    public function test_an_empty_channel_name_is_not_stamped(): void
    {
        $handler = new TestHandler();
        $logger = new Logger(new Monolog('x', [$handler]), new Dispatcher());

        (new LogChannelTap())->__invoke($logger, '');

        $captured = null;
        $logger->listen(function (MessageLogged $event) use (&$captured): void {
            $captured = $event;
        });

        $logger->info('hello');

        $this->assertSame('', LogChannelTap::extract($captured->context)[0]);
    }

    public function test_extract_tolerates_a_non_string_marker(): void
    {
        [$channel, $context] = LogChannelTap::extract([LogChannelTap::KEY => ['nope'], 'keep' => 1]);

        $this->assertSame('', $channel);
        $this->assertSame(['keep' => 1], $context);
    }

    public function test_strip_processor_is_idempotent(): void
    {
        $processor = new StripChannelMarkerProcessor();

        $record = new LogRecord(
            new \DateTimeImmutable(),
            'app',
            Level::Info,
            'hello',
            [LogChannelTap::KEY => 'single', 'a' => 1]
        );

        $once = $processor($record);
        $twice = $processor($once);

        $this->assertSame(['a' => 1], $once->context);
        $this->assertSame(['a' => 1], $twice->context);
        $this->assertSame('hello', $twice->message);
        $this->assertSame(Level::Info, $twice->level);
    }
}
