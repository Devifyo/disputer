<?php

namespace Tests;

use App\Support\Modules;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Module switches cache per PHP process; tests share one, real
        // requests do not - reset so one test's toggles cannot leak.
        Modules::flush();
    }
}
