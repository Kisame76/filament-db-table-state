<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState;

use Filament\Tables\Table;
use Kisame76\FilamentDbTableState\Livewire\PersistTableStateHook;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentDbTableStateServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-db-table-state')
            ->hasConfigFile('db-table-state')
            ->hasMigration('create_table_states_table');
    }

    public function packageRegistered(): void
    {
        // Livewire subscribes the component hooks it knows about while its own provider boots,
        // and a hook registered after that is never called. Providers boot in the order
        // Composer lists the packages, which put this one first only because "kisame76" sorts
        // ahead of "livewire". Registering when the application starts booting - after every
        // provider has registered, before the first one boots - does not depend on that order.
        $registerHook = function (): void {
            if (! config('db-table-state.enabled', true)) {
                return;
            }

            // Registered globally so every Filament table persists to the DB
            // automatically – consumers don't need to touch their pages.
            Livewire::componentHook(PersistTableStateHook::class);
        };

        $this->app->isBooted() ? $registerHook() : $this->app->booting($registerHook);
    }

    public function packageBooted(): void
    {
        if (! config('db-table-state.enabled', true)) {
            return;
        }

        if (config('db-table-state.auto_enable_persistence', false)) {
            $this->enableSessionPersistenceForAllTables();
        }
    }

    /**
     * Turn on Filament's native session persistence for every table, so there
     * is state for this package to mirror into the database. Applied as a
     * global default; individual tables can still opt out by chaining
     * ->persistFiltersInSession(false) (etc.) in their own table() method.
     */
    protected function enableSessionPersistenceForAllTables(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table
                ->persistFiltersInSession()
                ->persistSortInSession()
                ->persistSearchInSession()
                ->persistColumnSearchesInSession()
                ->persistColumnsInSession();
        });
    }
}
