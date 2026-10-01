<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Tests\Support;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;

/**
 * A real Filament table component with nothing opted in: no ->persist*InSession() call, so
 * whatever reaches the database from it got there through `auto_enable_persistence`.
 */
class ThingsTable extends TableComponent
{
    public function table(Table $table): Table
    {
        return $this->configureTable($table);
    }

    protected function configureTable(Table $table): Table
    {
        return $table
            ->query(Thing::query())
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'done' => 'Done']),
            ]);
    }

    public function render(): View
    {
        return view('things');
    }
}
