<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];
}
