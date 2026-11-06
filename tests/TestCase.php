<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mobile-build tests start without a content bundle, so the real snapshot never leaks into
     * them. Tests of the bundle point this at a fixture.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['itp.content_bundle' => base_path('tests/Fixtures/content/none.json')]);
    }
}
