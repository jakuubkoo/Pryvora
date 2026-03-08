<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_example(): void
    {
        $this->assertTrue(true);
    }

    public function test_addition(): void
    {
        $result = 2 + 2;
        $this->assertEquals(4, $result);
    }
}

