# Changelog

All notable changes to `filament-db-table-state` will be documented in this file.

## 1.0.0 - 2026-06-09

### Added

- Initial release.
- Mirrors Filament table state (filters, sort, search, column order & visibility) per user into the database, surviving new sessions and following users across devices.
- Global Livewire `ComponentHook` that seeds state on boot and snapshots it on dehydrate — zero per-table setup.
- `auto_enable_persistence` option to flip Filament's session persistence on for every table.
- Configurable storage table, user column and auth guard; `TableStatePersister::resolveUserIdUsing()` for custom user resolution.
