<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The pulse widget's interactivity ships in the addon dist JS, NOT inline
 * in the widget blade: Statamic 6 renders widget HTML through Vue's
 * DynamicHtmlRenderer, whose template compiler strips <script> tags.
 */
final class PulseWidgetScriptTest extends TestCase
{
    public function test_widget_blade_contains_no_inline_script(): void
    {
        $contents = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/cp/widgets/logbook_pulse.blade.php'
        );

        // A real inline script needs a closing tag; the blade only mentions
        // "<script" inside a comment explaining why none exist.
        $this->assertStringNotContainsString('</script>', $contents, 'Inline scripts are stripped by the v6 CP widget renderer — ship JS via $scripts instead');
    }

    public function test_dist_js_binds_the_pulse_filter_exactly_once(): void
    {
        $js = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/dist/statamic-logbook.js'
        );

        // Document-level delegation with a load-once guard: immune to Vue
        // re-mounting the widget subtree, never double-bound.
        $this->assertStringContainsString('window.__logbookLoaded', $js);
        $this->assertStringContainsString('data-lb-filter', $js);
    }
}
