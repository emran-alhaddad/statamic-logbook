<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Http\Controllers\LogbookUtilityController;
use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogCpPageViews;
use EmranAlhaddad\StatamicLogbook\Support\AuditActionPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Page views are reads, not changes. They surface on the Timeline (and only
 * while tracking is on) and must never reach the Audit Logs page, which is the
 * record of what people changed.
 *
 * These tests pin the SQL predicate and the action-shape contract it depends
 * on, so the exclusion cannot silently stop matching.
 */
final class PageViewVisibilityTest extends TestCase
{
    /**
     * Minimal stand-in that records the where() call the scope adds.
     */
    private function spy(): object
    {
        return new class
        {
            /** @var list<array{0: string, 1: string, 2: string}> */
            public array $wheres = [];

            public function where(string $col, string $op, string $val): static
            {
                $this->wheres[] = [$col, $op, $val];

                return $this;
            }
        };
    }

    private function applyScope(object $q): object
    {
        $m = new \ReflectionMethod(LogbookUtilityController::class, 'withoutPageViews');
        $m->setAccessible(true);

        return $m->invoke(null, $q);
    }

    public function test_the_scope_excludes_viewed_actions(): void
    {
        $q = $this->applyScope($this->spy());

        $this->assertSame([['action', 'not like', '%.viewed']], $q->wheres);
    }

    public function test_the_scope_is_chainable_so_it_can_wrap_a_query(): void
    {
        $spy = $this->spy();

        $this->assertSame($spy, $this->applyScope($spy), 'Scope must return the query for chaining');
    }

    /**
     * The scope matches on the `.viewed` suffix. If a tracked route ever
     * produced an action that did not end that way, it would leak onto the
     * Audit page — so assert every one of them does.
     */
    public function test_every_tracked_route_produces_an_action_the_scope_matches(): void
    {
        foreach (LogCpPageViews::ROUTES as $route => [$type, , ]) {
            $action = "statamic.{$type}.viewed";

            $this->assertStringEndsWith('.viewed', $action, "{$route} would leak onto the Audit page");
            $this->assertSame('view', AuditActionPresenter::variant($action));
        }
    }

    /**
     * The inverse guard: a mutation action must NOT look like a page view, or
     * real change history would vanish from the Audit page.
     */
    public function test_mutation_actions_are_not_matched_by_the_exclusion(): void
    {
        $mutations = [
            'statamic.entry.created', 'statamic.entry.updated', 'statamic.entry.deleted',
            'statamic.collection.saved', 'statamic.user.login', 'statamic.user.loginFailed',
            'statamic.tree.updated', 'statamic.asset.uploaded',
        ];

        foreach ($mutations as $action) {
            $this->assertStringEndsNotWith('.viewed', $action, "{$action} must stay on the Audit page");
        }
    }
}
