<?php

namespace tests;


use Kirby\Cms\App;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Default preparation for each test.
     *
     */
    public function setUp(): void
    {
        parent::setUp();
        $_POST = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        App::destroy();
    }
}
