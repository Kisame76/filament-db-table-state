<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Livewire;

use Kisame76\FilamentDbTableState\Support\TableStatePersister;
use Livewire\ComponentHook;

/**
 * Global Livewire hook that wires database persistence into every Filament
 * table component automatically – no trait or base class required.
 *
 * Lifecycle ordering (verified against Livewire v4):
 *   - boot()      fires on the `mount`/`hydrate` events, BEFORE the component's
 *                 own `bootedInteractsWithTable()` reads the session. So we seed
 *                 DB -> session here, and Filament then restores from it.
 *   - dehydrate() fires at the end of the request, AFTER Filament has written
 *                 the latest state to the session. We snapshot session -> DB.
 *
 * The persister self-guards: it no-ops for non-table components and guests.
 */
class PersistTableStateHook extends ComponentHook
{
    public function boot(): void
    {
        $this->persister()->seed($this->component);
    }

    public function dehydrate(): void
    {
        $this->persister()->snapshot($this->component);
    }

    protected function persister(): TableStatePersister
    {
        return app(TableStatePersister::class);
    }
}
