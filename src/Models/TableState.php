<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $state
 */
class TableState extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('db-table-state.table', 'table_states');
    }
}
