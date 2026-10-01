<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Tests\Integration;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kisame76\FilamentDbTableState\FilamentDbTableStateServiceProvider;
use Kisame76\FilamentDbTableState\Support\TableStatePersister;
use Kisame76\FilamentDbTableState\Tests\Support\User;
use Livewire\ComponentHookRegistry;
use Livewire\LivewireServiceProvider;
use ReflectionProperty;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * The package as an application boots it: its own service provider registered, Filament and
 * Livewire real, a table component mounted and rendered. The unit-level tests next door fake
 * the table component and so can only ever prove the persister agrees with the fake; this is
 * the layer where a Filament or Livewire release shows up as a failure here instead of as a
 * page that quietly stops remembering.
 */
abstract class IntegrationTestCase extends Orchestra
{
    /**
     * Whether `auto_enable_persistence` is on for this class. Read when the provider boots, so
     * it has to be decided before the application is, which is why it is a property and not a
     * call inside a test.
     */
    protected bool $autoEnablePersistence = false;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            TablesServiceProvider::class,
            FilamentServiceProvider::class,
            FilamentDbTableStateServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Filament's rendered views touch the encrypter. Test-only; it protects nothing.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('db-table-state.auto_enable_persistence', $this->autoEnablePersistence);
        $app['config']->set('view.paths', [
            ...$app['config']->get('view.paths', []),
            __DIR__.'/../Support/views',
        ]);
    }

    protected function setUp(): void
    {
        // Both of these are static, so they outlive the application a test boots and would
        // hand the next test a registry or a table default the package has not set up for it.
        // Livewire's list of component hooks is the one that matters most: Livewire subscribes
        // it when it boots, and once an earlier test has put the hook in the list a provider
        // that registers it too late still gets it - on every boot but the first. A real
        // process only ever has the first.
        (new ReflectionProperty(ComponentHookRegistry::class, 'componentHooks'))->setValue(null, []);

        // Filament 5 keeps `Table::configureUsing()` in a container-scoped manager, so a new
        // application starts clean. Filament 4 keeps it in a static on the class.
        if (property_exists(Table::class, 'configurations')) {
            (new ReflectionProperty(Table::class, 'configurations'))->setValue(null, []);
        }

        parent::setUp();

        TableStatePersister::resolveUserIdUsing(null);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('');
            $table->string('email')->nullable();
            $table->string('password')->default('');
            $table->timestamps();
        });

        Schema::create('things', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('open');
            $table->timestamps();
        });

        // The migration the package ships, not a hand-written copy of it.
        (require __DIR__.'/../../database/migrations/create_table_states_table.php.stub')->up();
    }

    protected function tearDown(): void
    {
        TableStatePersister::resolveUserIdUsing(null);

        parent::tearDown();
    }
}
