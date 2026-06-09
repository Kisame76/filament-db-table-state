<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Support;

use Closure;
use Illuminate\Support\Facades\Auth;
use Kisame76\FilamentDbTableState\Models\TableState;
use Throwable;

/**
 * Mirrors Filament's per-table session state into the database, scoped to the
 * authenticated user, so filters/sort/search/columns survive new sessions and
 * follow the user across devices.
 *
 * Filament already persists this state to the session (when the matching
 * ->persist*InSession() flags are on). We simply:
 *   - seed():     DB  -> session, before Filament reads it (on boot)
 *   - snapshot(): session -> DB, after Filament has written it (on dehydrate)
 *
 * All public entry points are fail-safe: persistence must never break a page.
 */
class TableStatePersister
{
    /**
     * Session-key resolver methods exposed by Filament's InteractsWithTable.
     * Each returns the unique (per Livewire component + tenant) session key for
     * one slice of table state.
     *
     * @var list<string>
     */
    protected const KEY_METHODS = [
        'getTableFiltersSessionKey',
        'getTableSortSessionKey',
        'getTableSearchSessionKey',
        'getTableColumnSearchesSessionKey',
        'getTableColumnsSessionKey',
        'getHasReorderedTableColumnsSessionKey',
        'getTablePerPageSessionKey',
    ];

    protected static ?Closure $userIdResolver = null;

    /**
     * Override how the current user id is resolved (e.g. custom guard or tenant
     * scoping). Call from a service provider's boot().
     */
    public static function resolveUserIdUsing(?Closure $resolver): void
    {
        static::$userIdResolver = $resolver;
    }

    /**
     * Whether this Livewire component is a Filament table we can persist.
     */
    public function appliesTo(object $component): bool
    {
        return method_exists($component, 'getTableFiltersSessionKey');
    }

    /**
     * Restore the user's saved state into the session for the keys this table
     * uses, without overwriting anything already present in the session.
     */
    public function seed(object $component): void
    {
        if (! $this->shouldRun($component)) {
            return;
        }

        try {
            $userId = $this->userId();

            if ($userId === null) {
                return;
            }

            $stored = $this->record($userId, $this->tableKey($component))?->state ?? [];

            if ($stored === []) {
                return;
            }

            foreach ($this->sessionKeys($component) as $key) {
                if (array_key_exists($key, $stored) && ! session()->has($key)) {
                    session()->put($key, $stored[$key]);
                }
            }
        } catch (Throwable) {
            // Fail-safe: never break the page over persistence.
        }
    }

    /**
     * Persist the current session state for this table into the user's row,
     * merging with any state from other tables. Only writes when changed.
     */
    public function snapshot(object $component): void
    {
        if (! $this->shouldRun($component)) {
            return;
        }

        try {
            $userId = $this->userId();

            if ($userId === null) {
                return;
            }

            $current = [];

            foreach ($this->sessionKeys($component) as $key) {
                if (session()->has($key)) {
                    $current[$key] = session()->get($key);
                }
            }

            if ($current === []) {
                return;
            }

            $tableKey = $this->tableKey($component);
            $existing = $this->record($userId, $tableKey)?->state ?? [];
            $merged = array_merge($existing, $current);

            if ($merged == $existing) {
                return;
            }

            TableState::query()->updateOrCreate(
                [$this->userColumn() => $userId, 'table_key' => $tableKey],
                ['state' => $merged],
            );
        } catch (Throwable) {
            // Fail-safe: never break the page over persistence.
        }
    }

    protected function shouldRun(object $component): bool
    {
        return (bool) config('db-table-state.enabled', true)
            && $this->appliesTo($component);
    }

    /**
     * Resolve the session keys this component uses for its persisted state.
     *
     * @return list<string>
     */
    protected function sessionKeys(object $component): array
    {
        $keys = [];

        foreach (self::KEY_METHODS as $method) {
            if (! method_exists($component, $method)) {
                continue;
            }

            try {
                $key = $component->{$method}();
            } catch (Throwable) {
                // Key not resolvable yet (e.g. table not configured) – skip it.
                continue;
            }

            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    protected function record(int|string $userId, string $tableKey): ?TableState
    {
        return TableState::query()
            ->where($this->userColumn(), $userId)
            ->where('table_key', $tableKey)
            ->first();
    }

    /**
     * Derive the shared table identifier from the component. Every session key
     * for a table shares the same `tables.<hash>` prefix, so we strip the
     * filters suffix to get a stable per-table key.
     */
    protected function tableKey(object $component): string
    {
        return (string) preg_replace('/_filters$/', '', $component->getTableFiltersSessionKey());
    }

    protected function userColumn(): string
    {
        return config('db-table-state.user_column', 'user_id');
    }

    protected function userId(): int|string|null
    {
        if (static::$userIdResolver !== null) {
            return (static::$userIdResolver)();
        }

        return Auth::guard(config('db-table-state.guard'))->id();
    }
}
