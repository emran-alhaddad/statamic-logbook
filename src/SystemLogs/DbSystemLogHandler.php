<?php

namespace EmranAlhaddad\StatamicLogbook\SystemLogs;

use Illuminate\Support\Facades\DB;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;

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

        DB::connection($conn)->table('logbook_system_logs')->insert([
            'level'      => strtolower($record->level->getName()),
            'message'    => (string) $record->message,
            'context'    => !empty($record->context) ? json_encode($record->context, JSON_UNESCAPED_UNICODE) : null,
            'channel'    => $this->forcedChannel ?: $record->channel,
            'request_id' => $requestId,
            'user_id'    => $userId,
            'ip'         => $ip,
            'method'     => $method,
            'url'        => $url,
            'user_agent' => $userAgent ?: null,
            'created_at' => now(),
        ]);
    }
}
