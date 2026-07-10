<?php

namespace PelicanMarketplace\PluginMarketplace\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base test case for this plugin's own, standalone test suite.
 *
 * Pelican deliberately never loads plugin code while running the
 * panel's own test suite (`PluginService::loadPlugins()` returns early
 * when `$this->app->runningUnitTests()`), so a plugin's tests can't run
 * as part of `php artisan test` in the host panel - they need their
 * own minimal Laravel application, which is exactly what Orchestra
 * Testbench provides. See docs/DEVELOPER.md for how to run these.
 *
 * Deliberately does NOT register `PluginMarketplaceProvider` here:
 * its `register()` method calls `App\Models\Role::registerCustomPermissions()`
 * and `App\Models\Subuser::registerCustomPermissions()`, and its
 * `boot()` queries `App\Models\Server` - all real Pelican application
 * classes that simply do not exist in an isolated Testbench app, so
 * registering the provider here would fail immediately with a
 * class-not-found error.
 * That is expected and correct: a Pelican plugin is not a
 * host-independent package, it is a tightly integrated extension of
 * one specific application, so permission registration and anything
 * else that touches `App\Models\*` is exercised via the manual QA
 * checklist in docs/DEVELOPER.md against a real Pelican install
 * instead. What *is* covered here is every service class whose logic
 * doesn't require those host models - the repository clients, caching,
 * version comparison, compatibility/dependency resolution, jar
 * validation and plugin.yml parsing - which is the large majority of
 * this plugin's actual logic.
 */
abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('plugin-marketplace', require __DIR__ . '/../config/plugin-marketplace.php');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
