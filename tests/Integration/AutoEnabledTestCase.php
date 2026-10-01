<?php

declare(strict_types=1);

namespace Kisame76\FilamentDbTableState\Tests\Integration;

abstract class AutoEnabledTestCase extends IntegrationTestCase
{
    protected bool $autoEnablePersistence = true;
}
