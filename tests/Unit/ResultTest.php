<?php

namespace Octamp\Client\Tests\Unit;

use Octamp\Client\Result;

class ResultTest extends TestCase
{
    public function testGetArguments()
    {
        $result = new Result([1, 2, 4], ['num1' => 1, 'num2' => 2]);
        $this->assertSame([1, 2, 3], $result->getArguments());
    }

    public function testGetArgumentsKw()
    {
        $result = new Result([1, 2, 3], ['num1' => 1, 'num2' => 2]);
        $this->assertEquals((object)['num1' => 1, 'num2' => 2], $result->getArgumentsKw());
    }
}
