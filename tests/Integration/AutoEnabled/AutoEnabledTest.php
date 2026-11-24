<?php

declare(strict_types=1);

use Kisame76\FilamentDbTableState\Models\TableState;
use Kisame76\FilamentDbTableState\Tests\Support\Thing;
use Kisame76\FilamentDbTableState\Tests\Support\ThingsTable;
use Kisame76\FilamentDbTableState\Tests\Support\User;
use Livewire\Livewire;

it('persists a table that opted into nothing when auto_enable_persistence is on', function (): void {
    $user = User::create(['name' => 'Ada']);
    Thing::create(['name' => 'Alpha']);
    Thing::create(['name' => 'Bravo']);

    Livewire::actingAs($user)
        ->test(ThingsTable::class)
        ->set('tableSearch', 'Alp')
        ->call('sortTable', 'name');

    $state = TableState::query()->where('user_id', $user->id)->sole()->state;

    $keys = collect($state)->keys();

    expect($keys->contains(fn (string $key): bool => (bool) preg_match('/[._]search$/', $key)))->toBeTrue()
        ->and($keys->contains(fn (string $key): bool => (bool) preg_match('/[._]sort$/', $key)))->toBeTrue();

    session()->flush();

    $table = Livewire::actingAs($user)
        ->test(ThingsTable::class)
        ->assertSet('tableSearch', 'Alp')
        ->assertSee('Alpha')
        ->assertDontSee('Bravo');

    expect($table->instance()->getTableSortColumn())->toBe('name');
});
