<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Tests\Support;

/**
 * Minimal stand-in for a Filament table Livewire component. It exposes the same
 * session-key resolver methods the real InteractsWithTable trait provides, so
 * the persister can be tested without booting Filament.
 */
class FakeTableComponent
{
    /**
     * $filtersHash mirrors Filament's tenant scoping: the filters key hashes
     * md5(class|tenant) while every other key hashes md5(class) only.
     */
    public function __construct(
        private string $hash = 'abc123',
        private ?string $filtersHash = null,
    ) {}

    public function getTableFiltersSessionKey(): string
    {
        $hash = $this->filtersHash ?? $this->hash;

        return "tables.{$hash}_filters";
    }

    public function getTableSortSessionKey(): string
    {
        return "tables.{$this->hash}_sort";
    }

    public function getTableSearchSessionKey(): string
    {
        return "tables.{$this->hash}_search";
    }

    public function getTableColumnSearchesSessionKey(): string
    {
        return "tables.{$this->hash}_column_search";
    }

    public function getTableColumnsSessionKey(): string
    {
        return "tables.{$this->hash}_columns";
    }

    public function getHasReorderedTableColumnsSessionKey(): string
    {
        return "tables.{$this->hash}_has_reordered_columns";
    }

    public function getTablePerPageSessionKey(): string
    {
        return "tables.{$this->hash}_per_page";
    }
}
