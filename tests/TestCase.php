<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kisame76\FilamentDbTableState\Support\TableStatePersister;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * The package's runtime wiring (the Livewire hook and Table::configureUsing)
 * boots Filament/Livewire and is verified end-to-end in a browser. These tests
 * exercise the storage core — the persister, model and config options — in
 * isolation, so the package's service provider is intentionally NOT registered.
 */
class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // The service provider isn't loaded, so seed the config manually.
        $app['config']->set('db-table-state', require __DIR__.'/../config/db-table-state.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        TableStatePersister::resolveUserIdUsing(null);
        $this->createStateTable();
    }

    protected function tearDown(): void
    {
        TableStatePersister::resolveUserIdUsing(null);

        parent::tearDown();
    }

    protected function createStateTable(?string $table = null, ?string $userColumn = null): void
    {
        $table ??= config('db-table-state.table');
        $userColumn ??= config('db-table-state.user_column');

        Schema::dropIfExists($table);

        Schema::create($table, function (Blueprint $blueprint) use ($userColumn): void {
            $blueprint->id();
            // Plain integer column (no FK) — these tests exercise the persister in
            // isolation and don't create a users table to constrain against.
            $blueprint->unsignedBigInteger($userColumn);
            $blueprint->string('table_key');
            $blueprint->json('state')->nullable();
            $blueprint->timestamps();
            $blueprint->unique([$userColumn, 'table_key']);
        });
    }
}
