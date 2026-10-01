<?php

declare(strict_types=1);

use Kisame76\FilamentDbTableState\Models\TableState;
use Kisame76\FilamentDbTableState\Support\TableStatePersister;
use Kisame76\FilamentDbTableState\Tests\Support\PersistingThingsTable;
use Kisame76\FilamentDbTableState\Tests\Support\Thing;
use Kisame76\FilamentDbTableState\Tests\Support\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::create(['name' => 'Ada']);

    Thing::create(['name' => 'Alpha', 'status' => 'open']);
    Thing::create(['name' => 'Bravo', 'status' => 'done']);
    Thing::create(['name' => 'Charlie', 'status' => 'open']);
});

/**
 * What a fresh login looks like to the table: the same user, a session that remembers nothing.
 */
function newSession(): void
{
    session()->flush();
}

it('mirrors search, sort and filters from a real table into one row', function (): void {
    Livewire::actingAs($this->user)
        ->test(PersistingThingsTable::class)
        ->set('tableSearch', 'Alp')
        ->call('sortTable', 'name')
        ->set('tableFilters.status.value', 'open');

    $row = TableState::query()->where('user_id', $this->user->id)->sole();

    expect($row->table_key)->toBe(PersistingThingsTable::class)
        ->and(collect($row->state)->keys()->implode(' '))
        ->toContain('_search')
        ->toContain('_sort')
        ->toContain('_filters')
        ->and(collect($row->state)->filter(fn ($value, $key) => str_ends_with($key, '_search'))->first())
        ->toBe('Alp');
});

it('restores that state into a new session before the table reads it', function (): void {
    Livewire::actingAs($this->user)
        ->test(PersistingThingsTable::class)
        ->set('tableSearch', 'Alp')
        ->call('sortTable', 'name')
        ->set('tableFilters.status.value', 'open');

    newSession();

    $table = Livewire::actingAs($this->user)
        ->test(PersistingThingsTable::class)
        ->assertSet('tableSearch', 'Alp')
        ->assertSet('tableFilters.status.value', 'open')
        ->assertSee('Alpha')
        ->assertDontSee('Charlie');

    // The property that holds the sort is named differently across Filament releases; the
    // getter is the stable way to ask.
    expect($table->instance()->getTableSortColumn())->toBe('name');
});

it('does not hand one user\'s state to another', function (): void {
    Livewire::actingAs($this->user)
        ->test(PersistingThingsTable::class)
        ->set('tableSearch', 'Alp');

    newSession();

    $someoneElse = User::create(['name' => 'Grace']);

    Livewire::actingAs($someoneElse)
        ->test(PersistingThingsTable::class)
        ->assertSet('tableSearch', '')
        ->assertSee('Charlie');

    // Visiting a table writes that user's own row, with an empty search in it. What matters
    // is that none of Ada's search came across.
    $searches = TableState::query()->where('user_id', $someoneElse->id)->get()
        ->flatMap(fn ($row) => collect($row->state ?? [])->filter(fn ($value, $key) => str_ends_with($key, '_search')));

    expect($searches->filter(fn ($value) => filled($value)))->toBeEmpty();
});

it('forgets a search once the user clears it, and the next session starts clean', function (): void {
    Livewire::actingAs($this->user)
        ->test(PersistingThingsTable::class)
        ->set('tableSearch', 'Alp')
        ->set('tableSearch', '');

    newSession();

    Livewire::actingAs($this->user)
        ->test(PersistingThingsTable::class)
        ->assertSet('tableSearch', '')
        ->assertSee('Charlie');
});

it('forgets a sort once it is cycled back to none', function (): void {
    $table = Livewire::actingAs($this->user)
        ->test(PersistingThingsTable::class)
        ->call('sortTable', 'name')   // ascending
        ->call('sortTable', 'name')   // descending
        ->call('sortTable', 'name');  // none

    newSession();

    Livewire::actingAs($this->user)
        ->test(PersistingThingsTable::class)
        ->assertSet('tableSortColumn', null);
});

it('writes nothing for a guest', function (): void {
    Livewire::test(PersistingThingsTable::class)->set('tableSearch', 'Alp');

    expect(TableState::query()->count())->toBe(0);
});

it('resolves the user through a custom resolver', function (): void {
    TableStatePersister::resolveUserIdUsing(fn () => $this->user->id);

    Livewire::test(PersistingThingsTable::class)->set('tableSearch', 'Alp');

    expect(TableState::query()->where('user_id', $this->user->id)->count())->toBe(1);
});

it('still finds every session key it manages on a real Filament table', function (): void {
    $table = Livewire::actingAs($this->user)->test(PersistingThingsTable::class)->instance();

    $managed = (new ReflectionClassConstant(TableStatePersister::class, 'KEY_METHODS'))->getValue();

    // A method Filament renamed would not fail anywhere else: the persister skips a key it
    // cannot resolve, so a table would simply stop remembering that one slice.
    foreach ($managed as $method) {
        expect(method_exists($table, $method))->toBeTrue("Filament's table no longer has {$method}()");
        expect($table->{$method}())->toBeString()->not->toBe('');
    }
});
