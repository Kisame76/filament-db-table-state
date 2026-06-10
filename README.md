<a href="https://github.com/Kisame76/filament-db-table-state" class="filament-hidden">
    <img src="https://repository-images.githubusercontent.com/1263977432/2540bf07-5b4a-4a93-b3ee-d46745564623" alt="Filament DB Table State" style="width: 100%; max-width: 100%;" class="filament-hidden">
</a>

# Filament DB Table State

[![Filament](https://img.shields.io/badge/Filament-4.x%20%7C%205.x-FdAE4B?style=flat-square&logo=laravel&logoColor=white)](https://filamentphp.com)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/kisame76/filament-db-table-state.svg?style=flat-square)](https://packagist.org/packages/kisame76/filament-db-table-state)
[![Tests](https://img.shields.io/github/actions/workflow/status/Kisame76/filament-db-table-state/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Kisame76/filament-db-table-state/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/kisame76/filament-db-table-state.svg?style=flat-square)](https://packagist.org/packages/kisame76/filament-db-table-state)

Persist Filament table state — **filters, sort, search, column order and column visibility** — per user in the **database**, instead of only the session.

Filament can already persist table state with `->persistFiltersInSession()`, `->persistSortInSession()`, `->persistColumnsInSession()` and friends. But the session is per-browser and short-lived: clear cookies, switch device or let the session expire and the state is gone.

This package mirrors that same state into a database row keyed by the user, so a user's filters and column layout **survive new sessions and follow them across devices**. By default it upgrades every table you already flag with `->persist*InSession()`; flip one switch to cover every table.

## How it works

Filament writes table state to the session (when the `->persist*InSession()` flags are on). This package hooks into Livewire globally and:

1. **Seeds** the session from the user's saved DB row on component boot — _before_ Filament's `bootedInteractsWithTable()` reads it.
2. **Snapshots** the session back to the DB at the end of the request — _after_ Filament has written the latest state.

It reuses Filament's own session keys (`getTableFiltersSessionKey()`, `getTableSortSessionKey()`, `getTableColumnsSessionKey()`, …), so it stays compatible with how Filament stores state. Each table gets its own small row per user — keyed by the table's Livewire component class (e.g. `App\Filament\Resources\Users\Pages\ListUsers`), so rows are easy to identify in the database — holding just that table's state.

## Requirements

- PHP 8.2+
- Filament v4 or v5
- Livewire v3.5+ / v4

## Installation

```bash
composer require kisame76/filament-db-table-state
```

Publish and run the migration (creates the `table_states` table):

```bash
php artisan vendor:publish --tag=filament-db-table-state-migrations
php artisan migrate
```

The migration adds a `user_id` foreign key with a cascade delete to your `users`
table. Using UUID/ULID keys or a custom users table? Edit the published migration
before running `migrate` — it has inline comments showing what to change.

That's it. Any table that uses Filament's session persistence (`->persistFiltersInSession()`, `->persistSortInSession()`, `->persistColumnsInSession()`, …) now also persists to the **database** — surviving new sessions and following the user across devices. To enable it for _every_ table automatically, set `auto_enable_persistence => true` (see below).

## Configuration (optional)

```bash
php artisan vendor:publish --tag=filament-db-table-state-config
```

```php
return [
    // Master switch. When false the package does nothing.
    'enabled' => env('DB_TABLE_STATE_ENABLED', true),

    // Default false = only tables you've flagged with ->persist*InSession()
    // are mirrored. Set true to flip those flags on for EVERY table.
    'auto_enable_persistence' => false,

    // Storage. user_column is a string so it supports integer and UUID keys.
    'table' => 'table_states',
    'user_column' => 'user_id',

    // Auth guard used to resolve the current user (null = default guard).
    'guard' => null,
];
```

### Custom user resolution

Need a custom guard or extra scoping? Set a resolver from any service provider's `boot()`:

```php
use Kisame76\FilamentDbTableState\Support\TableStatePersister;

TableStatePersister::resolveUserIdUsing(fn () => auth('admin')->id());
```

### What persists, and when

The package mirrors whatever Filament writes to the **session** — it never
touches the URL:

- **Column layout** (visibility + order): Filament persists this to the session
  _by default_, so it is **always** mirrored to the database, on every table,
  while the package is enabled.
- **Filters, sort, search**: Filament keeps these in the **URL query string** by
  default (not the session), so they are mirrored only once you opt the table in
  (or enable every table — see below).

### Default: opt in for filters, sort & search

Out of the box (`auto_enable_persistence => false`), turn on Filament's native
session persistence for the tables you care about:

```php
public function table(Table $table): Table
{
    return $table
        ->persistFiltersInSession()
        ->persistSortInSession();
    // column layout already persists by default
}
```

Those tables now persist filters and sort to the database as well — surviving
new sessions and following the user across devices.

### Filament's session-persistence methods

These are the native Filament `Table` methods this package mirrors to the
database. The defaults are Filament's own — note that only the column layout is
persisted out of the box:

| Method                               | Persists                  | Default |
| ------------------------------------ | ------------------------- | ------- |
| `->persistFiltersInSession()`        | Filters                   | off     |
| `->persistSortInSession()`           | Sort                      | off     |
| `->persistSearchInSession()`         | Table search              | off     |
| `->persistColumnSearchesInSession()` | Per-column searches       | off     |
| `->persistColumnsInSession()`        | Column visibility + order | **on**  |

Enable all of them on a single table:

```php
public function table(Table $table): Table
{
    return $table
        ->persistFiltersInSession()
        ->persistSortInSession()
        ->persistSearchInSession()
        ->persistColumnSearchesInSession()
        ->persistColumnsInSession();
}
```

Every method also accepts `false` to turn it off — handy when
`auto_enable_persistence` is on and you want to exclude one table, e.g.
`->persistColumnsInSession(false)`.

### Persist every table automatically

Set `auto_enable_persistence => true` to flip Filament's session persistence on
for **every** table via `Table::configureUsing()` — no per-table changes needed.

```php
// config/db-table-state.php
'auto_enable_persistence' => true,
```

When this is on, opt a single table back out by chaining the native flags in its
`table()` method — your call wins:

```php
public function table(Table $table): Table
{
    return $table
        ->persistFiltersInSession(false)
        ->persistColumnsInSession(false);
}
```

### Custom global combinations

`auto_enable_persistence => true` is a shortcut that turns _everything_ on for
every table. For any other global combination — say, persist filters on every
table but never persist columns — leave `auto_enable_persistence => false` and
configure Filament yourself in your app's `AppServiceProvider::boot()`:

```php
use Filament\Tables\Table;

public function boot(): void
{
    Table::configureUsing(function (Table $table): void {
        $table
            ->persistFiltersInSession()       // filters on every table → mirrored to the DB
            ->persistColumnsInSession(false); // never persist the column layout
    });
}
```

The package's hook mirrors whatever ends up in the session, so that is all you
need: filters now persist to the database everywhere, columns nowhere. Keep
`auto_enable_persistence => false` so the package doesn't flip its own defaults
on top of yours.

### Turning it off

Set `enabled => false` to switch the whole package off — no hook, no mirroring.

## Notes & caveats

- Each table has its own small row per user (keyed by user and the table's Livewire component class), so reads and writes only ever touch that one table's state.
- On panels using tenancy, Filament hashes the filters session key per tenant — those keys land in the same per-table row and are restored per tenant; nothing extra to configure.
- **Defaults of newly added filters don't reach users with saved state.** Filament only applies filter defaults (`->default()`) when _no_ stored filter state exists for the table: `bootedInteractsWithTable()` fills the filter form straight from the stored array, missing filters become inactive, and that inactive value is immediately persisted back. This is core Filament session behavior — the DB mirror just makes it survive session expiry. After deploying a new filter that has a default, clear the affected rows:

  ```sql
  DELETE FROM table_states WHERE table_key = 'App\Filament\Resources\Users\Pages\ListUsers';
  ```

  Note that the default still only applies once the user gets a **fresh session** (logout/login or session expiry): a live session still holds the old filter state, Filament keeps reading it from there, and the next request mirrors it right back into the database.

- Persistence is **fail-safe**: any error while reading/writing state is swallowed so it can never break a page.
- Two browser tabs editing the same table can race on the last write; the most recent request wins.

## License

MIT — see [LICENSE.md](LICENSE.md).
