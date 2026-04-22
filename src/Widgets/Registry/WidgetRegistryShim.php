<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Widgets\Registry;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Statamic\Widgets\Widget;

/**
 * Back-compat helper that re-registers Logbook widgets into
 * `app('statamic.widgets')` when — and only when — the core
 * binding does not already contain them.
 *
 * Background
 * ----------
 * Statamic v5.x exhibited a subtle interaction between the order of
 * addon service-provider registration and the `statamic.widgets`
 * binding, causing addon-declared widgets to be absent from the CP
 * dashboard widget picker despite being declared in `$widgets`. The
 * original fix was an eager `$this->app->bind('statamic.widgets', ...)`
 * in {@see \EmranAlhaddad\StatamicLogbook\LogbookServiceProvider::register()}
 * that replaced the core binding with a closure rebuilding the map
 * from `statamic.extensions`.
 *
 * That eager replacement is harmful on Statamic 6, whose
 * {@see \Statamic\Providers\ExtensionServiceProvider} installs its own
 * binding via `registerBindingAlias('widgets', Widget::class)` and
 * relies on it being left alone.
 *
 * This class lets us keep the v5 safety net while behaving correctly
 * on v6 by running **after** core boot and only acting when the core
 * binding is actually missing our handles.
 */
final class WidgetRegistryShim
{
    /**
     * @param  Container  $container  The application container.
     * @param  list<class-string<Widget>>  $widgets  The addon's widget classes.
     */
    public function __construct(
        private Container $container,
        private array $widgets
    ) {
    }

    /**
     * Apply the shim if, and only if, the core binding is missing at
     * least one of this addon's widget handles.
     *
     * Returns true when the shim actually took effect, false when the
     * core binding already had everything covered.
     */
    public function applyIfNeeded(): bool
    {
        if (! $this->coreExtensionsBindingIsUsable()) {
            return false;
        }

        $missing = $this->missingHandles();
        if ($missing === []) {
            return false;
        }

        foreach ($missing as $widgetClass) {
            $widgetClass::register();
        }

        return true;
    }

    /**
     * Determine whether the `statamic.extensions` binding exists and
     * points to a collection-like value we can reason about. When
     * Statamic has not booted yet (e.g. unit tests, early provider
     * boot), we skip silently.
     */
    private function coreExtensionsBindingIsUsable(): bool
    {
        if (! $this->container->bound('statamic.extensions')) {
            return false;
        }

        $extensions = $this->container->make('statamic.extensions');

        return $extensions instanceof Collection;
    }

    /**
     * Return the subset of `$this->widgets` whose handle is NOT
     * present in the core widget binding.
     *
     * @return list<class-string<Widget>>
     */
    private function missingHandles(): array
    {
        $currentHandles = $this->currentRegisteredHandles();

        $missing = [];
        foreach ($this->widgets as $widgetClass) {
            if (! is_string($widgetClass) || ! class_exists($widgetClass)) {
                continue;
            }
            if (! is_subclass_of($widgetClass, Widget::class)) {
                continue;
            }

            $handle = $widgetClass::handle();
            if (! isset($currentHandles[$handle])) {
                $missing[] = $widgetClass;
            }
        }

        return $missing;
    }

    /**
     * Flatten the extensions collection into a [handle => class] map,
     * reading only what we know to be safely typed.
     *
     * @return array<string, string>
     */
    private function currentRegisteredHandles(): array
    {
        $handles = [];

        /** @var Collection<string, mixed> $extensions */
        $extensions = $this->container->make('statamic.extensions');

        foreach ($extensions as $key => $value) {
            if ($value instanceof Collection) {
                foreach ($value as $innerKey => $innerValue) {
                    if (is_string($innerKey) && is_string($innerValue)) {
                        $handles[$innerKey] = $innerValue;
                    }
                }

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $innerKey => $innerValue) {
                    if (is_string($innerKey) && is_string($innerValue)) {
                        $handles[$innerKey] = $innerValue;
                    }
                }
            }
        }

        return $handles;
    }
}
