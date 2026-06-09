<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Lightweight Authenticatable so tests can `$this->be(new FakeUser(42))` and
 * exercise the default-guard resolution path without a users table.
 */
class FakeUser implements Authenticatable
{
    public function __construct(private int $id) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
