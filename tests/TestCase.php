<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The page tests use plain numeric URLs (/tracking?id=5) and plain ?q= filters; opaque URLs and hidden filters have their own tests (OpaqueUrlsTest, UrlKeyTest, HiddenFiltersTest).
        config(['taksh.opaque_urls.enabled' => false, 'taksh.hide_filters.enabled' => false]);
    }
}
