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
     * CP route name => [subject type, route parameter, settings page key].
     * Parameter null = the page itself is the subject (dashboard). The page
     * key ties each route to the "Tracked pages" switches in CP settings
     * (SettingsRepository::VIEW_PAGES — a test keeps the key sets aligned).
     *
     * @var array<string, array{0: string, 1: ?string, 2: string}>
     */
    public const ROUTES = [
        'statamic.cp.dashboard' => ['dashboard', null, 'dashboard'],

        // Collections
        'statamic.cp.collections.index' => ['collection_listing', null, 'collections'],
        'statamic.cp.collections.show' => ['collection', 'collection', 'collections'],
        'statamic.cp.collections.entries.index' => ['collection', 'collection', 'collections'],
        'statamic.cp.collections.tree.index' => ['collection', 'collection', 'collections'],
        'statamic.cp.collections.entries.edit' => ['entry', 'entry', 'entries'],

        // Taxonomies
        'statamic.cp.taxonomies.index' => ['taxonomy_listing', null, 'taxonomies'],
        'statamic.cp.taxonomies.show' => ['taxonomy', 'taxonomy', 'taxonomies'],
        'statamic.cp.taxonomies.terms.edit' => ['term', 'term', 'taxonomies'],

        // Navigation
        'statamic.cp.navigation.index' => ['nav_listing', null, 'navigation'],
        'statamic.cp.navigation.show' => ['nav', 'navigation', 'navigation'],

        // Globals
        'statamic.cp.globals.index' => ['globals_listing', null, 'globals'],
        'statamic.cp.globals.variables.edit' => ['globals', 'global_set', 'globals'],
        'statamic.cp.globals.edit' => ['globals', 'global_set', 'globals'],

        // Assets
        'statamic.cp.assets.browse.index' => ['asset_listing', null, 'assets'],
        'statamic.cp.assets.browse.show' => ['asset_container', 'container', 'assets'],
        'statamic.cp.asset-containers.show' => ['asset_container', 'asset_container', 'assets'],
        'statamic.cp.assets.show' => ['asset', 'encoded_asset', 'assets'],
        'statamic.cp.assets.edit' => ['asset', 'encoded_asset', 'assets'],

        // Users & access
        'statamic.cp.users.edit' => ['user', 'user', 'users'],
        'statamic.cp.users.index' => ['user_listing', null, 'user_listing'],
        'statamic.cp.user-groups.index' => ['user_group_listing', null, 'users'],
        'statamic.cp.roles.index' => ['role_listing', null, 'users'],

        // Forms
        'statamic.cp.forms.index' => ['form_listing', null, 'forms'],
        'statamic.cp.forms.show' => ['form', 'form', 'forms'],
        'statamic.cp.forms.submissions.index' => ['form', 'form', 'forms'],
        'statamic.cp.forms.submissions.show' => ['submission', 'submission', 'forms'],

        // Fields
        'statamic.cp.blueprints.index' => ['blueprint_listing', null, 'blueprints'],
        'statamic.cp.fieldsets.index' => ['fieldset_listing', null, 'blueprints'],
    ];

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
        if (! $request->isMethod('GET')) {
            return;
        }

        // Statamic 6's CP is Inertia-driven: clicking from one CP page to
        // another is an axios XHR, which sets X-Requested-With and so looks
        // like ajax() — but it IS a page view and must be recorded, or only
        // hard browser reloads ever get logged.
        //
        // A partial reload (X-Inertia-Partial-Component) re-fetches props for
        // the page you are already on, so it is not a new view.
        if ($request->hasHeader('X-Inertia')) {
            if ($request->hasHeader('X-Inertia-Partial-Component')) {
                return;
            }
        } elseif ($request->expectsJson() || $request->ajax()) {
            // A genuine data/API XHR (autosave, field validation, our own
            // .json endpoints) — not a page the user opened.
            return;
        }
        if (! is_object($response) || ! method_exists($response, 'getStatusCode') || $response->getStatusCode() !== 200) {
            return;
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName === '' || ! isset(self::ROUTES[$routeName])) {
            return;
        }

        [$type, $param, $pageKey] = self::ROUTES[$routeName];

        // Per-page switches from CP settings. An absent config list means
        // "all pages" (unconfigured installs).
        $pages = config('logbook.activity.pages');
        if (is_array($pages) && ! in_array($pageKey, $pages, true)) {
            return;
        }

        if ($this->userIsExcluded()) {
            return;
        }

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

    /**
     * Role-based exclusions from CP settings ("who gets tracked").
     */
    private function userIsExcluded(): bool
    {
        $excluded = (array) config('logbook.activity.excluded_roles', []);
        if ($excluded === []) {
            return false;
        }

        try {
            $user = auth()->user();
            if ($user === null) {
                return false;
            }

            if (in_array(\EmranAlhaddad\StatamicLogbook\Support\SettingsRepository::EXCLUDE_SUPERS, $excluded, true)
                && method_exists($user, 'isSuper') && $user->isSuper()) {
                return true;
            }

            if (method_exists($user, 'roles')) {
                foreach ($user->roles() as $role) {
                    $handle = is_object($role) && method_exists($role, 'handle') ? $role->handle() : (string) $role;
                    if (in_array($handle, $excluded, true)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            // Unknown user shape — track rather than silently skip.
        }

        return false;
    }

    private function recentlyViewed(string $type, ?string $subjectId, string $userId): bool
    {
        $minutes = (int) config('logbook.activity.dedupe_minutes', 5);
        if ($minutes < 1) {
            return false; // dedupe disabled
        }

        try {
            $conn = DbConnectionResolver::resolve();

            return DB::connection($conn)->table('logbook_audit_logs')
                ->where('action', 'statamic.'.$type.'.viewed')
                ->where('user_id', $userId)
                ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
                ->where('created_at', '>=', now()->subMinutes($minutes))
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
