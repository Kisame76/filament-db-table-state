<?php

declare(strict_types=1);

use Kisame76\FilamentDbTableState\Models\TableState;
use Kisame76\FilamentDbTableState\Support\TableStatePersister;
use Kisame76\FilamentDbTableState\Tests\Support\FakeTableComponent;

beforeEach(function (): void {
    TableStatePersister::resolveUserIdUsing(fn () => 7);
});

function persister(): TableStatePersister
{
    return new TableStatePersister;
}

it('snapshots session state into the database', function (): void {
    session()->put('tables.abc_filters', ['status' => 'open']);
    session()->put('tables.abc_sort', 'created_at:desc');

    persister()->snapshot(new FakeTableComponent('abc'));

    $row = TableState::query()->where('user_id', 7)->first();

    expect($row)->not->toBeNull()
        ->and($row->state['tables.abc_filters'])->toBe(['status' => 'open'])
        ->and($row->state['tables.abc_sort'])->toBe('created_at:desc');
});

it('seeds the session from the database', function (): void {
    TableState::create([
        'user_id' => 7,
        'table_key' => 'tables.abc',
        'state' => ['tables.abc_filters' => ['status' => 'closed']],
    ]);

    expect(session()->has('tables.abc_filters'))->toBeFalse();

    persister()->seed(new FakeTableComponent('abc'));

    expect(session()->get('tables.abc_filters'))->toBe(['status' => 'closed']);
});

it('does not overwrite existing session values when seeding', function (): void {
    TableState::create([
        'user_id' => 7,
        'table_key' => 'tables.abc',
        'state' => ['tables.abc_filters' => ['status' => 'closed']],
    ]);

    session()->put('tables.abc_filters', ['status' => 'live']);

    persister()->seed(new FakeTableComponent('abc'));

    expect(session()->get('tables.abc_filters'))->toBe(['status' => 'live']);
});

it('stores each table in its own row', function (): void {
    session()->put('tables.abc_filters', ['y' => 2]);
    persister()->snapshot(new FakeTableComponent('abc'));

    session()->put('tables.xyz_filters', ['z' => 3]);
    persister()->snapshot(new FakeTableComponent('xyz'));

    expect(TableState::query()->where('user_id', 7)->count())->toBe(2)
        ->and(TableState::query()->where('table_key', 'tables.abc')->first()->state)
        ->toBe(['tables.abc_filters' => ['y' => 2]])
        ->and(TableState::query()->where('table_key', 'tables.xyz')->first()->state)
        ->toBe(['tables.xyz_filters' => ['z' => 3]]);
});

it('merges keys within the same table across snapshots', function (): void {
    session()->put('tables.abc_filters', ['x' => 1]);
    persister()->snapshot(new FakeTableComponent('abc'));

    session()->put('tables.abc_sort', 'created_at:desc');
    persister()->snapshot(new FakeTableComponent('abc'));

    $state = TableState::query()->where('user_id', 7)->first()->state;

    expect($state)->toHaveKeys(['tables.abc_filters', 'tables.abc_sort']);
});

it('keeps a single row per user per table when snapshotting repeatedly', function (): void {
    session()->put('tables.abc_filters', ['a' => 1]);
    persister()->snapshot(new FakeTableComponent('abc'));
    persister()->snapshot(new FakeTableComponent('abc'));

    expect(TableState::query()->where('user_id', 7)->count())->toBe(1);
});

it('is a no-op for guests', function (): void {
    TableStatePersister::resolveUserIdUsing(fn () => null);

    session()->put('tables.abc_filters', ['a' => 1]);

    persister()->snapshot(new FakeTableComponent('abc'));

    expect(TableState::query()->count())->toBe(0);
});

it('is a no-op when the package is disabled', function (): void {
    config()->set('db-table-state.enabled', false);

    session()->put('tables.abc_filters', ['a' => 1]);

    persister()->snapshot(new FakeTableComponent('abc'));

    expect(TableState::query()->count())->toBe(0);
});

it('does not seed when disabled', function (): void {
    config()->set('db-table-state.enabled', false);

    TableState::create([
        'user_id' => 7,
        'table_key' => 'tables.abc',
        'state' => ['tables.abc_filters' => ['status' => 'closed']],
    ]);

    persister()->seed(new FakeTableComponent('abc'));

    expect(session()->has('tables.abc_filters'))->toBeFalse();
});
