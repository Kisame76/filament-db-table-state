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
     * Each returns the unique session key for one slice of table state. The
     * filters key is additionally scoped per tenant (md5(class|tenant)) while
     * all other keys hash the component class only.
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
     * Persist the current session state for this table into the user's row.
     *
     * For each session key this table manages we either write the current
     * session value or, when the user has cleared that slice, remove it from
     * the stored state. Keys this table does NOT manage (e.g. another tenant's
     * filters) are left untouched. Only writes when the result actually changed.
     *
     * Removal is essential: when a user resets sorting back to default, Filament
     * stores null under the sort session key, and Laravel's session()->has()
     * reports false for null. A plain array_merge can only add or overwrite, so
     * the previously saved sort would otherwise linger forever and be re-seeded
     * on the next request – making it impossible to clear sorting.
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

            $tableKey = $this->tableKey($component);
            $existing = $this->record($userId, $tableKey)?->state ?? [];
            $merged = $existing;

            foreach ($this->sessionKeys($component) as $key) {
                if (session()->has($key)) {
                    $merged[$key] = session()->get($key);
                } else {
                    // Cleared or never set (null counts as cleared) – drop it so
                    // the default state is restored on the next request.
                    unset($merged[$key]);
                }
            }

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
     * Stable per-table identifier: the Livewire component class hosting the
     * table. Session-key prefixes are unusable here — Filament hashes the
     * filters key per tenant but every other key per class, so under
     * multi-tenancy no single prefix identifies the table.
     */
    protected function tableKey(object $component): string
    {
        return $component::class;
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
