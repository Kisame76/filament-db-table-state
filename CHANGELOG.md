# Changelog

All notable changes to `filament-db-table-state` will be documented in this file.

## Unreleased

### Changed

- **Requires Filament 4.3 or newer on the v4 line** (`^4.3|^5.0`, was `^4.0|^5.0`). The `auto_enable_persistence` option calls `Table::persistColumnsInSession()`, which Filament only added in 4.3.0; on 4.0 to 4.2 it threw `BadMethodCallException`. Filament 5 is unaffected, and Composer already installs the newest 4.x for anyone upgrading.
- `orchestra/testbench` accepts `^11.0`, so the suite can run against Laravel 13 (it previously resolved to Laravel 12 at most).

### Fixed

- The Livewire hook is now registered when the application starts booting rather than from this package's own `boot()`. Livewire subscribes the component hooks it knows about while its provider boots, and a hook registered after that is never called, so nothing was mirrored and nothing was restored. It worked only because Composer lists `kisame76/…` ahead of `livewire/…`, which decides the order providers boot in; an application that registers providers in another order, or a package that sorts after Livewire, lost persistence without an error.

### Added

- `SECURITY.md` with a reporting address and the scope worth reviewing.
- Tests that mount a real Filament table through Livewire with the package's own service provider registered: a search, sort and filter written to the database, restored into a new session, kept from another user, cleared again, and the session keys the persister manages checked against the ones Filament really provides. The existing tests fake the table component and could not notice Filament changing under the package.
- A release workflow: pushing a `v*` tag publishes the GitHub release, with the notes taken from the matching section of this file. A tag without a section fails instead of publishing an empty release.
- CI now runs the suite on PHP 8.2 to 8.5 against Laravel 11, 12 and 13 and Filament 4 and 5, plus one run on the lowest versions Composer allows. Workflow actions are pinned to commit SHAs and kept current by Dependabot.

## 1.0.2 - 2026-06-17

### Fixed

- Clearing a slice of table state is now persisted. Resetting a sort back to default (Filament stores `null` under the sort session key, and Laravel's `session()->has()` reports `false` for `null`) meant `snapshot()` never saw the change, while its `array_merge` could only add or overwrite — never remove. The previously saved sort lingered in the database and was re-seeded on the next request, so sorting could not be cleared. `snapshot()` now removes any managed key that is absent or `null` in the session, while leaving keys it does not manage (e.g. another tenant's filters) untouched. The same fix applies to clearing filters, search and per-page.

## 1.0.1 - 2026-06-10

### Fixed

- One row per table under multi-tenancy. `table_key` is now the table's Livewire component class (FQCN) instead of a prefix derived from the filters session key. Filament hashes the filters key per tenant (`md5(class|tenant)`) but every other key per class (`md5(class)`), so the old derivation created a separate row per tenant and mixed key prefixes inside `state`. As a bonus, rows are now human-readable in the database.

### Upgrade

- Rows written by 1.0.0 use the old hash-based `table_key` and are simply ignored after upgrading. Clean them up with `DELETE FROM table_states;` — state re-saves on next use. Live sessions still carry the old session state until users log out or the session expires.

## 1.0.0 - 2026-06-09

### Added

- Initial release.
- Mirrors Filament table state (filters, sort, search, column order & visibility) per user into the database, surviving new sessions and following users across devices.
- Global Livewire `ComponentHook` that seeds state on boot and snapshots it on dehydrate — zero per-table setup.
- `auto_enable_persistence` option to flip Filament's session persistence on for every table.
- Configurable storage table, user column and auth guard; `TableStatePersister::resolveUserIdUsing()` for custom user resolution.
