<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests should validate application behavior, not require a
        // compiled frontend bundle. Production and normal development still
        // use the real @vite manifest; only PHPUnit replaces Vite with the
        // framework-provided test fake.
        $this->withoutVite();
    }
}
