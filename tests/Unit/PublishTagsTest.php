<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\LogbookServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Stands in for the Statamic addon record, which needs a real
 * `vendor/composer/installed.json` entry to resolve.
 */
final class FakeAddonSlug
{
    public function slug(): string
    {
        return 'statamic-logbook';
    }
}

final class TestableLogbookServiceProvider extends LogbookServiceProvider
{
    protected function getAddon()
    {
        return new FakeAddonSlug();
    }

    /** @param  array<string,string>  $paths */
    public function fakeStatamicAssetPublishes(array $paths): void
    {
        $this->publishes($paths, 'statamic-logbook');
    }

    public function runRegisterPublishTagAliases(): void
    {
        $this->registerPublishTagAliases();
    }
}

/**
 * Issue #4: `vendor:publish --tag=logbook` reported "No publishable
 * resources", because Statamic tags asset publishes with the addon slug
 * and skips `bootConfig()` for a config file that is not named after the
 * slug.
 */
final class PublishTagsTest extends TestCase
{
    private const CSS = '/pkg/resources/dist/statamic-logbook.min.css';
    private const JS = '/pkg/resources/dist/statamic-logbook.min.js';

    private TestableLogbookServiceProvider $provider;

    protected function setUp(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([]));
        Container::setInstance($app);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);

        $this->resetPublishRegistry();

        $this->provider = new TestableLogbookServiceProvider($app);
        $this->provider->fakeStatamicAssetPublishes([
            self::CSS => $app->publicPath('vendor/statamic-logbook/css/statamic-logbook.min.css'),
            self::JS => $app->publicPath('vendor/statamic-logbook/js/statamic-logbook.min.js'),
        ]);
        $this->provider->runRegisterPublishTagAliases();
    }

    protected function tearDown(): void
    {
        $this->resetPublishRegistry();
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        Container::setInstance(null);
    }

    private function resetPublishRegistry(): void
    {
        $reflection = new \ReflectionClass(ServiceProvider::class);
        $reflection->setStaticPropertyValue('publishes', []);
        $reflection->setStaticPropertyValue('publishGroups', []);
    }

    private function sources(string $tag): array
    {
        $paths = array_map(
            fn (string $path): string => realpath($path) ?: $path,
            array_keys(ServiceProvider::pathsToPublish(null, $tag))
        );
        sort($paths);

        return $paths;
    }

    public function test_the_logbook_tag_publishes_the_config_and_both_bundles(): void
    {
        $this->assertSame(
            [realpath(dirname(__DIR__, 2).'/config/logbook.php'), self::CSS, self::JS],
            $this->sources('logbook')
        );
    }

    public function test_the_logbook_assets_tag_publishes_only_the_bundles(): void
    {
        $this->assertSame([self::CSS, self::JS], $this->sources('logbook-assets'));
    }

    public function test_the_logbook_config_tag_still_publishes_only_the_config(): void
    {
        $this->assertSame(
            [realpath(dirname(__DIR__, 2).'/config/logbook.php')],
            $this->sources('logbook-config')
        );
    }

    /**
     * Statamic force-publishes the slug tag after `statamic:install`, so
     * adding the config there would clobber a host's edited
     * `config/logbook.php` on every `composer update`.
     */
    public function test_the_slug_tag_never_force_overwrites_the_config(): void
    {
        $this->assertSame([self::CSS, self::JS], $this->sources('statamic-logbook'));
    }

    public function test_assets_publish_to_the_paths_statamic_uses(): void
    {
        $targets = ServiceProvider::pathsToPublish(null, 'logbook-assets');

        $this->assertStringEndsWith(
            'public/vendor/statamic-logbook/css/statamic-logbook.min.css',
            $targets[self::CSS]
        );
        $this->assertStringEndsWith(
            'public/vendor/statamic-logbook/js/statamic-logbook.min.js',
            $targets[self::JS]
        );
    }
}
