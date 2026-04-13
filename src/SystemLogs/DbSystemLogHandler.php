<?php

namespace EmranAlhaddad\StatamicLogbook\SystemLogs;

use Illuminate\Support\Facades\DB;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use EmranAlhaddad\StatamicLogbook\Support\LogSpool;
use EmranAlhaddad\StatamicLogbook\Support\Sanitizer;


class DbSystemLogHandler extends AbstractProcessingHandler
{
    private ?string $forcedChannel;

    public function __construct(Level $level = Level::Debug, bool $bubble = true, ?string $channel = null)
    {
        parent::__construct($level, $bubble);
        $this->forcedChannel = $channel;
    }

    protected function write(LogRecord $record): void
    {
        $this->persist(
            level: strtolower($record->level->getName()),
            message: (string) $record->message,
            context: is_array($record->context ?? null) ? $record->context : [],
            channel: $this->forcedChannel ?: $record->channel
        );
    }

    public function recordMessage(string $level, string $message, array $context = []): void
    {
        $this->persist(
            level: strtolower($level),
            message: $message,
            context: $context,
            channel: $this->forcedChannel ?: 'logbook'
        );
    }

    private function persist(string $level, string $message, array $context, ?string $channel): void
    {
        try {
            $conn = DbConnectionResolver::resolve();

            $request = function_exists('request') ? request() : null;
            $userId = function_exists('auth') ? optional(auth()->user())->id : null;
            $userId = $userId ? (string) $userId : null;
            $ip = null;
            $method = null;
            $url = null;
            $userAgent = null;

            if ($request) {
                try {
                    $ip = $request->ip();
                    $method = $request->method();
                    $url = $request->fullUrl();
                    $userAgent = (string) $request->userAgent();
                } catch (\Throwable $e) {
                    // ignore request parsing failures
                }
            }

            $requestId = null;
            if ($request) {
                $requestId = $request->attributes->get('logbook_request_id')
                    ?? $request->headers->get('X-Request-Id');
            }

            $ctx = Sanitizer::maskArray(is_array($context) ? $context : []);
            $row = [
                'level'      => $level,
                'message'    => $message,
                'context'    => !empty($ctx)
                    ? json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'channel'    => $channel,
                'request_id' => $requestId,
                'user_id'    => $userId,
                'ip'         => $ip,
                'method'     => $method,
                'url'        => $url,
                'user_agent' => $userAgent ?: null,
                'created_at' => now(),
            ];

            if (LogSpool::enabled()) {
                LogSpool::enqueueSystem($row);
                return;
            }

            DB::connection($conn)->table('logbook_system_logs')->insert([
                'level'      => $row['level'],
                'message'    => $row['message'],
                'context'    => $row['context'],
                'channel'    => $row['channel'],
                'request_id' => $row['request_id'],
                'user_id'    => $row['user_id'],
                'ip'         => $row['ip'],
                'method'     => $row['method'],
                'url'        => $row['url'],
                'user_agent' => $row['user_agent'],
                'created_at' => $row['created_at'],
            ]);
        } catch (\Throwable $e) {
            // Never break the app because a logging sink failed.
        }
    }
}
