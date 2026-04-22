<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Widgets\LogbookPulseWidget;
use EmranAlhaddad\StatamicLogbook\Widgets\LogbookStatsWidget;
use EmranAlhaddad\StatamicLogbook\Widgets\LogbookTrendsWidget;
use EmranAlhaddad\StatamicLogbook\Widgets\Registry\WidgetRegistryShim;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Statamic\Widgets\Widget;

final class WidgetRegistryShimTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();

        // The RegistersItself trait inside Statamic calls the global
        // `app()` helper when a widget self-registers. That helper
        // resolves to whatever Container::getInstance() returns — so we
        // must install *this* container as the global instance for the
        // shim's $widgetClass::register() invocation to mutate the same
        // 'statamic.extensions' binding we're asserting against.
        Container::setInstance($this->container);
    }

    protected function tearDown(): void
    {
        // Avoid leaking the test container into sibling tests.
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_no_op_when_statamic_extensions_binding_is_missing(): void
    {
        $shim = new WidgetRegistryShim($this->container, [LogbookStatsWidget::class]);
        $this->assertFalse($shim->applyIfNeeded());
    }

    public function test_no_op_when_core_already_has_all_addon_handles(): void
    {
        $extensions = collect([
            Widget::class => collect([
                LogbookStatsWidget::handle() => LogbookStatsWidget::class,
                LogbookTrendsWidget::handle() => LogbookTrendsWidget::class,
                LogbookPulseWidget::handle() => LogbookPulseWidget::class,
            ]),
        ]);
        $this->container->instance('statamic.extensions', $extensions);

        $shim = new WidgetRegistryShim($this->container, [
            LogbookStatsWidget::class,
            LogbookTrendsWidget::class,
            LogbookPulseWidget::class,
        ]);

        $this->assertFalse($shim->applyIfNeeded());
    }

    public function test_applies_when_core_binding_is_missing_a_handle(): void
    {
        // Only two of three widgets present in the core registry.
        $registry = collect([
            LogbookStatsWidget::handle() => LogbookStatsWidget::class,
            LogbookTrendsWidget::handle() => LogbookTrendsWidget::class,
        ]);
        $extensions = new Collection([
            Widget::class => $registry,
        ]);
        $this->container->instance('statamic.extensions', $extensions);

        $shim = new WidgetRegistryShim($this->container, [
            LogbookStatsWidget::class,
            LogbookTrendsWidget::class,
            LogbookPulseWidget::class,
        ]);

        $this->assertTrue($shim->applyIfNeeded());

        // After the shim runs, the registered map should contain the
        // missing handle (LogbookPulseWidget::register() mutates the
        // same collection via the RegistersItself trait).
        $this->assertArrayHasKey(
            LogbookPulseWidget::handle(),
            $extensions[Widget::class]->all()
        );
    }

    public function test_skips_non_string_or_non_widget_entries_in_configured_list(): void
    {
        $extensions = collect([
            Widget::class => collect([]),
        ]);
        $this->container->instance('statamic.extensions', $extensions);

        // A non-class-string slipped into $widgets should NOT cause a failure.
        $shim = new WidgetRegistryShim($this->container, [
            LogbookStatsWidget::class,
            'Not\\A\\Class',
            \stdClass::class, // not a Widget subclass
        ]);

        $this->assertTrue($shim->applyIfNeeded());
    }
}
