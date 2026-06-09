<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Optional Filament plugin object.
 *
 * Database persistence is wired up globally by
 * {@see FilamentDbTableStateServiceProvider} as soon as the package is
 * installed, so registering this plugin is NOT required.
 *
 * It is provided for discoverability and for users who prefer the explicit
 * panel API:
 *
 *     ->plugin(FilamentDbTableStatePlugin::make())
 */
class FilamentDbTableStatePlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-db-table-state';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
