<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Kisame76\FilamentDbTableState\Models\TableState;
use Kisame76\FilamentDbTableState\Support\TableStatePersister;
use Kisame76\FilamentDbTableState\Tests\Support\FakeTableComponent;
use Kisame76\FilamentDbTableState\Tests\Support\FakeUser;

beforeEach(function (): void {
    TableStatePersister::resolveUserIdUsing(fn () => 7);
});

it('respects a custom table name', function (): void {
    config()->set('db-table-state.table', 'user_table_prefs');
    $this->createStateTable(table: 'user_table_prefs');

    session()->put('tables.abc_filters', ['a' => 1]);

    (new TableStatePersister)->snapshot(new FakeTableComponent('abc'));

    expect((new TableState)->getTable())->toBe('user_table_prefs')
        ->and(DB::table('user_table_prefs')->where('user_id', 7)->exists())->toBeTrue();
});

it('respects a custom user_column', function (): void {
    config()->set('db-table-state.user_column', 'member_id');
    $this->createStateTable(userColumn: 'member_id');

    session()->put('tables.abc_filters', ['a' => 1]);

    (new TableStatePersister)->snapshot(new FakeTableComponent('abc'));

    expect(DB::table(config('db-table-state.table'))->where('member_id', 7)->exists())->toBeTrue();
});

it('seeds correctly with a custom user_column', function (): void {
    config()->set('db-table-state.user_column', 'member_id');
    $this->createStateTable(userColumn: 'member_id');

    DB::table(config('db-table-state.table'))->insert([
        'member_id' => 7,
        'table_key' => 'tables.abc',
        'state' => json_encode(['tables.abc_filters' => ['status' => 'closed']]),
    ]);

    (new TableStatePersister)->seed(new FakeTableComponent('abc'));

    expect(session()->get('tables.abc_filters'))->toBe(['status' => 'closed']);
});

it('resolves the user id through a custom resolver (guard override)', function (): void {
    TableStatePersister::resolveUserIdUsing(fn () => 99);

    session()->put('tables.abc_filters', ['a' => 1]);

    (new TableStatePersister)->snapshot(new FakeTableComponent('abc'));

    expect(TableState::query()->where('user_id', 99)->exists())->toBeTrue();
});

it('falls back to the configured auth guard when no resolver is set', function (): void {
    TableStatePersister::resolveUserIdUsing(null);
    $this->be(new FakeUser(42));

    session()->put('tables.abc_filters', ['a' => 1]);

    (new TableStatePersister)->snapshot(new FakeTableComponent('abc'));

    expect(TableState::query()->where('user_id', 42)->exists())->toBeTrue();
});
