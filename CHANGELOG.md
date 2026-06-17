# Changelog

All notable changes to `filament-db-table-state` will be documented in this file.

## 1.0.2 - 2026-06-17

### Fixed

- Clearing a slice of table state is now persisted. Resetting a sort back to default (Filament stores `null` under the sort session key, and Laravel's `session()->has()` reports `false` for `null`) meant `snapshot()` never saw the change, while its `array_merge` could only add or overwrite — never remove. The previously saved sort lingered in the database and was re-seeded on the next request, so sorting could not be cleared. `snapshot()` now removes any managed key that is absent or `null` in the session, while leaving keys it does not manage (e.g. another tenant's filters) untouched. The same fix applies to clearing filters, search and per-page.

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
