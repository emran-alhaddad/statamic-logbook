<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Support\AssetPublisher;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

/**
 * Issue #4: Statamic only force-publishes addon assets from
 * `statamic:install`, so a CP can be left rendering against a missing
 * or stale bundle. The CP middleware self-heals, and must never throw
 * doing it.
 */
final class AssetPublisherTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        AssetPublisher::reset();

        $this->root = sys_get_temp_dir().'/logbook-assets-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/public', 0775, true);

        $app = new Application($this->root);
        $app->instance('config', new Repository(['logbook' => ['assets' => ['auto_publish' => true]]]));
        Container::setInstance($app);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        AssetPublisher::reset();
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        Container::setInstance(null);
        exec('rm -rf '.escapeshellarg($this->root));
    }

    private function published(string $relative): string
    {
        return $this->root.'/public/vendor/statamic-logbook/'.$relative;
    }

    private function shipped(string $relative): string
    {
        return dirname(__DIR__, 2).'/resources/dist/'.$relative;
    }

    // ------------------------------------------------------------------
    // Staleness rules
    // ------------------------------------------------------------------

    public function test_a_missing_publish_is_stale(): void
    {
        $this->assertTrue(AssetPublisher::isStale(
            $this->shipped('statamic-logbook.min.css'),
            $this->published('css/statamic-logbook.min.css')
        ));
    }

    public function test_a_publish_of_a_different_size_is_stale(): void
    {
        $target = $this->root.'/stale.css';
        file_put_contents($target, 'x');

        $this->assertTrue(AssetPublisher::isStale($this->shipped('statamic-logbook.min.css'), $target));
    }

    public function test_an_older_publish_of_the_same_size_is_stale(): void
    {
        $source = $this->root.'/source.css';
        $target = $this->root.'/target.css';
        file_put_contents($source, 'abc');
        file_put_contents($target, 'xyz');
        touch($target, time() - 120);
        touch($source, time());

        $this->assertTrue(AssetPublisher::isStale($source, $target));
    }

    public function test_a_current_publish_is_not_stale(): void
    {
        $source = $this->root.'/source.css';
        $target = $this->root.'/target.css';
        file_put_contents($source, 'abc');
        copy($source, $target);
        touch($target, time() + 5);

        $this->assertFalse(AssetPublisher::isStale($source, $target));
    }

    public function test_a_missing_source_is_never_stale(): void
    {
        $this->assertFalse(AssetPublisher::isStale($this->root.'/nope.css', $this->root.'/also-nope.css'));
    }

    // ------------------------------------------------------------------
    // Publishing
    // ------------------------------------------------------------------

    public function test_it_publishes_both_bundles_where_statamic_expects_them(): void
    {
        AssetPublisher::ensurePublished();

        foreach (['css/statamic-logbook.min.css', 'js/statamic-logbook.min.js'] as $relative) {
            $this->assertFileExists($this->published($relative));
            $this->assertSame(
                file_get_contents($this->shipped(basename($relative))),
                file_get_contents($this->published($relative))
            );
        }
    }

    public function test_it_replaces_a_stale_publish(): void
    {
        $target = $this->published('css/statamic-logbook.min.css');
        mkdir(dirname($target), 0775, true);
        file_put_contents($target, '/* an old build */');

        AssetPublisher::ensurePublished();

        $this->assertSame(
            file_get_contents($this->shipped('statamic-logbook.min.css')),
            file_get_contents($target)
        );
    }

    public function test_it_runs_once_per_process(): void
    {
        AssetPublisher::ensurePublished();

        $target = $this->published('css/statamic-logbook.min.css');
        file_put_contents($target, '/* hand-edited */');

        AssetPublisher::ensurePublished();

        $this->assertSame('/* hand-edited */', file_get_contents($target));
    }

    public function test_the_opt_out_is_honoured(): void
    {
        config(['logbook.assets.auto_publish' => false]);

        AssetPublisher::ensurePublished();

        $this->assertFileDoesNotExist($this->published('css/statamic-logbook.min.css'));
    }

    public function test_forcing_ignores_both_the_opt_out_and_the_once_guard(): void
    {
        config(['logbook.assets.auto_publish' => false]);

        AssetPublisher::ensurePublished(force: true);
        AssetPublisher::ensurePublished(force: true);

        $this->assertFileExists($this->published('css/statamic-logbook.min.css'));
    }

    public function test_an_unwritable_public_directory_does_not_throw(): void
    {
        chmod($this->root.'/public', 0500);

        try {
            AssetPublisher::ensurePublished();
            $this->assertTrue(true, 'A read-only public/ is a normal production setup.');
        } finally {
            chmod($this->root.'/public', 0775);
        }
    }
}
