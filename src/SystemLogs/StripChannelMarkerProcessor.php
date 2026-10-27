<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\SystemLogs;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Removes {@see LogChannelTap::KEY} from a Monolog record so the channel
 * marker Logbook adds for its own bookkeeping never shows up in the
 * host's `laravel.log`, Slack messages, or anywhere else a handler
 * writes.
 *
 * Idempotent, and safe to run more than once per record — a `stack`
 * channel inherits one copy of this processor per sub-channel.
 */
final class StripChannelMarkerProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (! array_key_exists(LogChannelTap::KEY, $record->context)) {
            return $record;
        }

        $context = $record->context;
        unset($context[LogChannelTap::KEY]);

        return $record->with(context: $context);
    }
}
