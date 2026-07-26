<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Http\Middleware;

use Closure;
use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records CP page-view activity ("who opened what") as audit rows.
 *
 * Statamic fires no events for reads, so views are captured at the HTTP
 * layer: full-page GETs whose route name appears in ROUTES below. JSON/XHR
 * listing calls, POSTs and Logbook's own pages are never recorded.
 *
 * Rows share the audit table under `statamic.{type}.viewed` actions, so
 * the existing timeline/filters/retention all apply. Enabled only when
 * `logbook.activity.enabled` is on (CP settings: "Page views").
 */
class LogCpPageViews
{
    /**
     * CP route name => [subject type, route parameter holding the subject].
     * Parameter null = the page itself is the subject (dashboard).
     *
     * @var array<string, array{0: string, 1: ?string}>
     */
    private const ROUTES = [
        'statamic.cp.dashboard' => ['dashboard', null],
        'statamic.cp.collections.show' => ['collection', 'collection'],
        'statamic.cp.collections.entries.edit' => ['entry', 'entry'],
        'statamic.cp.taxonomies.show' => ['taxonomy', 'taxonomy'],
        'statamic.cp.taxonomies.terms.edit' => ['term', 'term'],
        'statamic.cp.navigation.show' => ['nav', 'navigation'],
        'statamic.cp.globals.variables.edit' => ['globals', 'global_set'],
        'statamic.cp.globals.edit' => ['globals', 'global_set'],
        'statamic.cp.assets.browse.show' => ['asset_container', 'container'],
        'statamic.cp.users.edit' => ['user', 'user'],
        'statamic.cp.users.index' => ['user_listing', null],
        'statamic.cp.forms.show' => ['form', 'form'],
        'statamic.cp.forms.submissions.show' => ['submission', 'submission'],
    ];

    /**
     * Repeated opens of the same thing by the same user inside this window
     * collapse into one row.
     * ponytail: fixed 5-minute window; make it a setting if anyone asks.
     */
    private const DEDUPE_MINUTES = 5;

    public function __construct(private AuditRecorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Runs after the response has been sent — view logging must never
     * slow down or break a CP page.
     */
    public function terminate(Request $request, $response): void
    {
        try {
            $this->record($request, $response);
        } catch (\Throwable) {
            // Never let activity logging surface as a CP error.
        }
    }

    private function record(Request $request, $response): void
    {
        if (! (bool) config('logbook.activity.enabled', false)) {
            return;
        }
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return;
        }
        if (! is_object($response) || ! method_exists($response, 'getStatusCode') || $response->getStatusCode() !== 200) {
            return;
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName === '' || ! isset(self::ROUTES[$routeName])) {
            return;
        }

        [$type, $param] = self::ROUTES[$routeName];

        $subjectId = null;
        $handle = null;
        if ($param !== null) {
            $raw = $request->route()->parameter($param);
            $subjectId = is_object($raw)
                ? (method_exists($raw, 'id') ? (string) $raw->id() : null)
                : (is_scalar($raw) ? (string) $raw : null);
            $handle = is_object($raw) && method_exists($raw, 'handle') ? (string) $raw->handle() : $subjectId;
        }

        $user = auth()->user();
        $userId = $user ? (string) ($user->id ?? '') : '';

        // Refresh-spam guard: same user, same subject, same action, recent.
        if ($userId !== '' && $this->recentlyViewed($type, $subjectId, $userId)) {
            return;
        }

        $this->recorder->record([
            'action' => 'statamic.'.$type.'.viewed',
            'subject_type' => $type,
            'subject_id' => $subjectId,
            'subject_handle' => $handle,
            'subject_title' => $this->subjectTitle($request, $param) ?? $handle,
            'changes' => null,
            'meta' => ['route' => $routeName, 'url' => $request->path()],
            'user_id' => $userId !== '' ? $userId : null,
            'user_email' => $user ? ($user->email ?? null) : null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function recentlyViewed(string $type, ?string $subjectId, string $userId): bool
    {
        try {
            $conn = DbConnectionResolver::resolve();

            return DB::connection($conn)->table('logbook_audit_logs')
                ->where('action', 'statamic.'.$type.'.viewed')
                ->where('user_id', $userId)
                ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
                ->where('created_at', '>=', now()->subMinutes(self::DEDUPE_MINUTES))
                ->exists();
        } catch (\Throwable) {
            // If the check fails, prefer a duplicate row over a lost one.
            return false;
        }
    }

    private function subjectTitle(Request $request, ?string $param): ?string
    {
        if ($param === null) {
            return null;
        }

        $raw = $request->route()->parameter($param);
        if (! is_object($raw)) {
            return null;
        }

        try {
            if (method_exists($raw, 'get') && is_scalar($t = $raw->get('title'))) {
                return (string) $t;
            }
            if (method_exists($raw, 'title') && is_scalar($t = $raw->title())) {
                return (string) $t;
            }
            if (method_exists($raw, 'email')) {
                return (string) $raw->email();
            }
        } catch (\Throwable) {
            // Title is decoration; never fail the row over it.
        }

        return null;
    }
}
