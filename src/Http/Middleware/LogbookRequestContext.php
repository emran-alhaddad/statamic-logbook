<?php

namespace EmranAlhaddad\StatamicLogbook\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogbookRequestContext
{
    public function handle($request, Closure $next)
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

        // store it on request so handler can read it
        $request->attributes->set('logbook_request_id', $requestId);

        // enrich Laravel log context
        Log::withContext([
            'request_id' => $requestId,
            'user_id' => optional(auth()->user())->id,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);

        return $next($request);
    }
}
