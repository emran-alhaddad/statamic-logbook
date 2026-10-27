<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\SystemLogs;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

/**
 * Laravel log "tap" that labels a channel's records with the channel's
 * own (Laravel) name, so the name survives all the way to our
 * {@see \Illuminate\Log\Events\MessageLogged} listener.
 *
 * Why this exists
 * ---------------
 * `MessageLogged` carries only `level`, `message` and `context` — never
 * the channel. Monolog's `$record->channel` is no help either: Laravel
 * names every Monolog logger `$config['name'] ?? app.env`, so a channel
 * without an explicit `name` reports "production", not "deprecations".
 *
 * The one value that travels from a resolved channel into
 * `MessageLogged` is the Laravel logger's own context
 * ({@see Logger::withContext()}) — `Illuminate\Log\Logger::writeLog()`
 * merges it into the context it dispatches — so that is where we put
 * the channel name.
 *
 * That marker would otherwise be written into the host's log files too,
 * so the tap also pushes {@see StripChannelMarkerProcessor}, which
 * drops the key from the Monolog record before any handler formats it.
 * Processor order is irrelevant: the strip is idempotent, always runs
 * before handlers, and never touches the separate context array the
 * event is dispatched with.
 *
 * Registered lazily, per channel, through `logging.channels.*.tap`, so
 * attaching it resolves no channels (and opens no Slack/Papertrail
 * socket) at boot.
 */
final class LogChannelTap
{
    /** Context key carrying the Laravel channel name. */
    public const KEY = '__logbook_channel';

    public function __invoke(Logger $logger, string $channel = ''): void
    {
        if ($channel === '') {
            return;
        }

        $logger->withContext([self::KEY => $channel]);

        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(new StripChannelMarkerProcessor());
        }
    }

    /**
     * Read the channel name out of a dispatched context, and hand back
     * the context with the marker removed so it never reaches storage.
     *
     * @param  array<array-key,mixed>  $context
     * @return array{0: string, 1: array<array-key,mixed>}
     */
    public static function extract(array $context): array
    {
        $channel = $context[self::KEY] ?? null;
        unset($context[self::KEY]);

        return [is_string($channel) ? $channel : '', $context];
    }
}
