<?php

declare(strict_types=1);

use Kisame76\FilamentDbTableState\Tests\Integration\AutoEnabledTestCase;
use Kisame76\FilamentDbTableState\Tests\Integration\IntegrationTestCase;
use Kisame76\FilamentDbTableState\Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(IntegrationTestCase::class)->group('rendered')->in('Integration/Mirroring');
uses(AutoEnabledTestCase::class)->group('rendered')->in('Integration/AutoEnabled');
