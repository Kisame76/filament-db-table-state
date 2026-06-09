<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the package registers nothing: no Livewire hook and no
    | global table configuration. Filament falls back to its default
    | session-only persistence behaviour.
    |
    */

    'enabled' => env('DB_TABLE_STATE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-enable Filament's native session persistence on every table
    |--------------------------------------------------------------------------
    |
    | This package mirrors whatever Filament writes to the SESSION into the
    | database. Filament persists the COLUMN layout (visibility + order) to the
    | session by default, so columns are always mirrored while enabled. Filters,
    | sort and search live in the URL by default (not the session), so they are
    | only mirrored once their ->persist*InSession() flag is set.
    |
    | Default (false): filters/sort/search are mirrored only on tables where you
    | set those flags yourself. Set to true to flip them on for EVERY table
    | automatically via Table::configureUsing(); you can still opt a single
    | table out again with e.g. ->persistFiltersInSession(false).
    |
    */

    'auto_enable_persistence' => false,

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The table that holds one row per user per table, and the column used to
    | reference the user. The published migration defines this column as a
    | foreignId with a cascade delete. If you use UUID/ULID user keys or a
    | non-standard users table, adjust that column in the published migration.
    |
    */

    'table' => 'table_states',

    'user_column' => 'user_id',

    /*
    |--------------------------------------------------------------------------
    | Auth guard
    |--------------------------------------------------------------------------
    |
    | Guard used to resolve the current user id. Null uses the default guard.
    | For full control, call TableStatePersister::resolveUserIdUsing() from a
    | service provider instead.
    |
    */

    'guard' => null,

];
