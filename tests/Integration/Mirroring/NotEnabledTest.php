<?php

declare(strict_types=1);

use Kisame76\FilamentDbTableState\Models\TableState;
use Kisame76\FilamentDbTableState\Tests\Support\ThingsTable;
use Kisame76\FilamentDbTableState\Tests\Support\User;
use Livewire\Livewire;

it('leaves a table that opted into nothing alone while auto_enable_persistence is off', function (): void {
    $user = User::create(['name' => 'Ada']);

    Livewire::actingAs($user)
        ->test(ThingsTable::class)
        ->set('tableSearch', 'Alp')
        ->call('sortTable', 'name');

    // Search and sort live in the URL until a table asks for the session, so there is nothing
    // for the package to mirror. Column layout is the one slice Filament keeps by default.
    $keys = TableState::query()->get()->flatMap(fn ($row) => array_keys($row->state ?? []));

    expect($keys->filter(fn ($key) => str_ends_with($key, '_search') || str_ends_with($key, '_sort')))->toBeEmpty();
});
