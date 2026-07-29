<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Http\Controllers\LogbookUtilityController;
use PHPUnit\Framework\TestCase;

/**
 * Regression cover for the Timeline query-parameter handling.
 *
 * Query params are crawler/attacker controlled. `?q[]=x` used to reach a
 * (string) cast and return a 500, and the severity filter used to test for a
 * value ('audit') that the allow-list could never contain, which silently
 * emptied the audit stream whenever any severity pill was selected.
 */
final class TimelineFiltersTest extends TestCase
{
    private function scalarParam(mixed $v): string
    {
        $m = new \ReflectionMethod(LogbookUtilityController::class, 'scalarParam');
        $m->setAccessible(true);

        return $m->invoke(null, $v);
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function stringList(mixed $v, array $allowed): array
    {
        $m = new \ReflectionMethod(LogbookUtilityController::class, 'stringList');
        $m->setAccessible(true);

        return $m->invoke(null, $v, $allowed);
    }

    public function test_array_input_never_reaches_a_string_cast(): void
    {
        // These are the shapes that produced a 500.
        $this->assertSame('', $this->scalarParam(['x']));
        $this->assertSame('', $this->scalarParam([['nested']]));
        $this->assertSame('', $this->scalarParam(new \stdClass));
        $this->assertSame('', $this->scalarParam(null));
    }

    public function test_scalar_input_is_preserved(): void
    {
        $this->assertSame('2026-07-28', $this->scalarParam('2026-07-28'));
        $this->assertSame('200', $this->scalarParam(200));
        $this->assertSame('1', $this->scalarParam(true));
    }

    public function test_string_list_filters_to_allowed_values(): void
    {
        $this->assertSame(['audit'], $this->stringList(['audit', 'bogus'], ['system', 'audit']));
        $this->assertSame(['system'], $this->stringList('system', ['system', 'audit']));
    }

    public function test_string_list_drops_nested_arrays_without_erroring(): void
    {
        // `?types[][]=x` — array_intersect on a nested array warned/failed.
        $this->assertSame([], $this->stringList([['x']], ['system', 'audit']));
        $this->assertSame([], $this->stringList(null, ['system', 'audit']));
        $this->assertSame([], $this->stringList([123, 4.5, true], ['system', 'audit']));
    }

    /**
     * The severity allow-list is system log levels only. If 'audit' ever
     * appears here it means someone reintroduced the coupling that made
     * selecting a severity wipe the audit stream.
     */
    public function test_severity_values_are_log_levels_only(): void
    {
        $this->assertSame(
            [],
            $this->stringList(['audit'], ['error', 'warn', 'info']),
            "'audit' is not a severity — it must not be filterable as one"
        );

        foreach (['error', 'warn', 'info'] as $sev) {
            $this->assertSame([$sev], $this->stringList([$sev], ['error', 'warn', 'info']));
        }
    }
}
