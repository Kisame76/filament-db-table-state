<?php

declare(strict_types=1);

use Kisame76\FilamentDbTableState\Models\TableState;
use Kisame76\FilamentDbTableState\Support\TableStatePersister;
use Kisame76\FilamentDbTableState\Tests\Support\FakeTableComponent;
use Kisame76\FilamentDbTableState\Tests\Support\OtherFakeTableComponent;

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
        ->and($row->table_key)->toBe(FakeTableComponent::class)
        ->and($row->state['tables.abc_filters'])->toBe(['status' => 'open'])
        ->and($row->state['tables.abc_sort'])->toBe('created_at:desc');
});

it('seeds the session from the database', function (): void {
    TableState::create([
        'user_id' => 7,
        'table_key' => FakeTableComponent::class,
        'state' => ['tables.abc_filters' => ['status' => 'closed']],
    ]);

    expect(session()->has('tables.abc_filters'))->toBeFalse();

    persister()->seed(new FakeTableComponent('abc'));

    expect(session()->get('tables.abc_filters'))->toBe(['status' => 'closed']);
});

it('does not overwrite existing session values when seeding', function (): void {
    TableState::create([
        'user_id' => 7,
        'table_key' => FakeTableComponent::class,
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
    persister()->snapshot(new OtherFakeTableComponent('xyz'));

    expect(TableState::query()->where('user_id', 7)->count())->toBe(2)
        ->and(TableState::query()->where('table_key', FakeTableComponent::class)->first()->state)
        ->toBe(['tables.abc_filters' => ['y' => 2]])
        ->and(TableState::query()->where('table_key', OtherFakeTableComponent::class)->first()->state)
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

it('removes a cleared (null) sort key so default sorting can be restored', function (): void {
    // Persist an active sort first.
    session()->put('tables.abc_sort', 'priority:asc');
    persister()->snapshot(new FakeTableComponent('abc'));

    expect(TableState::query()->where('user_id', 7)->first()->state)
        ->toBe(['tables.abc_sort' => 'priority:asc']);

    // User resets sorting to default: Filament writes null under the sort key,
    // and Laravel's session()->has() reports false for null.
    session()->put('tables.abc_sort', null);
    expect(session()->has('tables.abc_sort'))->toBeFalse();

    persister()->snapshot(new FakeTableComponent('abc'));

    // The stale sort must be gone, not lingering from a merge.
    expect(TableState::query()->where('user_id', 7)->first()->state)
        ->not->toHaveKey('tables.abc_sort');
});

it('removing one slice does not wipe other persisted slices', function (): void {
    session()->put('tables.abc_filters', ['status' => 'open']);
    session()->put('tables.abc_sort', 'priority:asc');
    persister()->snapshot(new FakeTableComponent('abc'));

    // Clear only the sort; keep the filter in session.
    session()->put('tables.abc_sort', null);
    persister()->snapshot(new FakeTableComponent('abc'));

    $state = TableState::query()->where('user_id', 7)->first()->state;

    expect($state)->toBe(['tables.abc_filters' => ['status' => 'open']]);
});

it('does not delete another tenant\'s filter key when clearing sort', function (): void {
    // Two tenants' filters live in the same row; only the active tenant's keys
    // are managed by a given component instance.
    session()->put('tables.abc|17_filters', ['project_id' => 5]);
    persister()->snapshot(new FakeTableComponent('abc', filtersHash: 'abc|17'));

    // Active tenant 3 clears its sort. Tenant 17's filter must survive.
    session()->put('tables.abc|3_filters', ['project_id' => 9]);
    session()->put('tables.abc_sort', null);
    persister()->snapshot(new FakeTableComponent('abc', filtersHash: 'abc|3'));

    $state = TableState::query()->where('user_id', 7)->first()->state;

    expect($state)->toHaveKey('tables.abc|17_filters')
        ->and($state['tables.abc|17_filters'])->toBe(['project_id' => 5])
        ->and($state)->not->toHaveKey('tables.abc_sort');
});

it('keeps a single row per user per table when snapshotting repeatedly', function (): void {
    session()->put('tables.abc_filters', ['a' => 1]);
    persister()->snapshot(new FakeTableComponent('abc'));
    persister()->snapshot(new FakeTableComponent('abc'));

    expect(TableState::query()->where('user_id', 7)->count())->toBe(1);
});

it('keeps tenant-scoped filter keys in the same table row', function (): void {
    // Under multi-tenancy Filament hashes the filters session key per tenant,
    // so its prefix differs from every other key of the same table.
    session()->put('tables.abc|17_filters', ['project_id' => 5]);
    session()->put('tables.abc_columns', ['name']);

    persister()->snapshot(new FakeTableComponent('abc', filtersHash: 'abc|17'));

    $rows = TableState::query()->where('user_id', 7)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->table_key)->toBe(FakeTableComponent::class)
        ->and($rows->first()->state)->toHaveKeys(['tables.abc|17_filters', 'tables.abc_columns']);
});

it('keeps one row per table across tenants and seeds only the active tenant', function (): void {
    session()->put('tables.abc|17_filters', ['project_id' => 5]);
    persister()->snapshot(new FakeTableComponent('abc', filtersHash: 'abc|17'));

    session()->put('tables.abc|3_filters', ['project_id' => 9]);
    persister()->snapshot(new FakeTableComponent('abc', filtersHash: 'abc|3'));

    expect(TableState::query()->where('user_id', 7)->count())->toBe(1);

    session()->flush();

    persister()->seed(new FakeTableComponent('abc', filtersHash: 'abc|3'));

    expect(session()->get('tables.abc|3_filters'))->toBe(['project_id' => 9])
        ->and(session()->has('tables.abc|17_filters'))->toBeFalse();
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
        'table_key' => FakeTableComponent::class,
        'state' => ['tables.abc_filters' => ['status' => 'closed']],
    ]);

    persister()->seed(new FakeTableComponent('abc'));

    expect(session()->has('tables.abc_filters'))->toBeFalse();
});
