<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Tests\Support;

use Filament\Tables\Table;

/**
 * The same table, opted in the way the README tells a user to: Filament's own session
 * persistence is on, and this package mirrors that session into the database.
 */
class PersistingThingsTable extends ThingsTable
{
    public function table(Table $table): Table
    {
        return $this->configureTable($table)
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession();
    }
}
