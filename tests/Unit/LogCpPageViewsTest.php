<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogCpPageViews;
use EmranAlhaddad\StatamicLogbook\Support\AuditActionPresenter;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use PHPUnit\Framework\TestCase;

final class LogCpPageViewsTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    protected function setUp(): void
    {
        $this->rows = [];

        $container = new Container;
        $container->instance('config', new Repository([
            'logbook' => ['activity' => ['enabled' => true]],
        ]));
        $container->instance(\Illuminate\Contracts\Auth\Factory::class, new class implements \Illuminate\Contracts\Auth\Guard
        {
            public function check(): bool
            {
                return false;
            }

            public function guest(): bool
            {
                return true;
            }

            public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
            {
                return null;
            }

            public function id()
            {
                return null;
            }

            public function validate(array $credentials = []): bool
            {
                return false;
            }

            public function hasUser(): bool
            {
                return false;
            }

            public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
            {
            }
        });
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function test_a_mapped_page_view_records_a_viewed_row(): void
    {
        $this->terminate($this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']));

        $this->assertCount(1, $this->rows);
        $this->assertSame('statamic.entry.viewed', $this->rows[0]['action']);
        $this->assertSame('entry', $this->rows[0]['subject_type']);
        $this->assertSame('e-1', $this->rows[0]['subject_id']);
    }

    public function test_nothing_is_recorded_when_activity_is_disabled(): void
    {
        config(['logbook.activity.enabled' => false]);

        $this->terminate($this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']));

        $this->assertSame([], $this->rows);
    }

    public function test_a_page_switched_off_in_settings_is_not_recorded(): void
    {
        config(['logbook.activity.pages' => ['dashboard', 'collections']]);

        $this->terminate($this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']));

        $this->assertSame([], $this->rows);
    }

    public function test_pages_left_on_in_settings_are_still_recorded(): void
    {
        config(['logbook.activity.pages' => ['dashboard', 'entries']]);

        $this->terminate($this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']));

        $this->assertCount(1, $this->rows);
    }

    public function test_an_absent_page_list_means_every_page_is_tracked(): void
    {
        // Installs that predate the per-page switches have no list at all.
        config(['logbook.activity.pages' => null]);

        $this->terminate($this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']));

        $this->assertCount(1, $this->rows);
    }

    public function test_unmapped_routes_are_ignored(): void
    {
        $this->terminate($this->request('statamic.cp.utilities.logbook.audit', []));

        $this->assertSame([], $this->rows);
    }

    public function test_json_requests_are_ignored(): void
    {
        $request = $this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']);
        $request->headers->set('Accept', 'application/json');

        $this->terminate($request);

        $this->assertSame([], $this->rows);
    }

    public function test_an_inertia_page_visit_is_recorded(): void
    {
        // Statamic 6 CP navigation: axios XHR (looks like ajax) + X-Inertia.
        $request = $this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('X-Inertia', 'true');

        $this->terminate($request);

        $this->assertCount(1, $this->rows, 'Inertia CP navigation must count as a page view');
    }

    public function test_an_inertia_partial_reload_is_not_a_new_view(): void
    {
        $request = $this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Partial-Component', 'Entries/Edit');

        $this->terminate($request);

        $this->assertSame([], $this->rows);
    }

    public function test_a_plain_data_xhr_is_still_ignored(): void
    {
        $request = $this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $this->terminate($request);

        $this->assertSame([], $this->rows);
    }

    public function test_non_get_requests_are_ignored(): void
    {
        $request = $this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1'], 'POST');

        $this->terminate($request);

        $this->assertSame([], $this->rows);
    }

    public function test_error_responses_are_ignored(): void
    {
        $this->terminate($this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']), 404);

        $this->assertSame([], $this->rows);
    }

    public function test_a_broken_recorder_never_breaks_the_page(): void
    {
        $middleware = new LogCpPageViews(new class extends AuditRecorder
        {
            public function __construct()
            {
            }

            public function record(array $payload): void
            {
                throw new \RuntimeException('boom');
            }
        });

        $middleware->terminate($this->request('statamic.cp.collections.entries.edit', ['entry' => 'e-1']), new Response('', 200));

        // Reaching this line without an exception IS the assertion.
        $this->assertTrue(true);
    }

    public function test_route_page_keys_align_with_settings_view_pages(): void
    {
        $settingsKeys = array_keys(\EmranAlhaddad\StatamicLogbook\Support\SettingsRepository::VIEW_PAGES);

        $routeKeys = [];
        foreach (LogCpPageViews::ROUTES as $route => [, , $pageKey]) {
            $this->assertContains($pageKey, $settingsKeys, "Route {$route} uses page key '{$pageKey}' with no settings switch");
            $routeKeys[] = $pageKey;
        }

        foreach ($settingsKeys as $key) {
            $this->assertContains($key, $routeKeys, "Settings page '{$key}' maps to no tracked route");
        }
    }

    public function test_every_mapped_route_action_has_a_human_label(): void
    {
        $const = new \ReflectionClassConstant(LogCpPageViews::class, 'ROUTES');

        foreach ($const->getValue() as $route => [$type, $param]) {
            $label = AuditActionPresenter::label("statamic.{$type}.viewed");

            $this->assertStringNotContainsString('statamic.', $label, "Action for {$route} has no human label");
        }
    }

    private function request(string $routeName, array $params, string $method = 'GET'): Request
    {
        $request = Request::create('/cp/test', $method);

        $route = new Route([$method], '/cp/test', []);
        $route->name($routeName);
        $route->bind($request);
        foreach ($params as $k => $v) {
            $route->setParameter($k, $v);
        }

        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function terminate(Request $request, int $status = 200): void
    {
        $recorder = new class($this->rows) extends AuditRecorder
        {
            /** @param list<array<string, mixed>> $rows */
            public function __construct(public array &$rows)
            {
            }

            public function record(array $payload): void
            {
                $this->rows[] = $payload;
            }
        };

        (new LogCpPageViews($recorder))->terminate($request, new Response('', $status));
    }
}
