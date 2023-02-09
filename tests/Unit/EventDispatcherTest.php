<?php

namespace Octamp\Client\Tests\Unit;

use Octamp\Client\EventDispatcher;

class EventDispatcherTest extends TestCase
{
    public function testOn()
    {
        $dispatcher = new EventDispatcher();
        $callback =  function () {};
        $dispatcher->on('octamp.test', $callback);

        $events = $dispatcher->getEvents('octamp.test');
        $this->assertIsArray($events);
        $this->assertCount(1, $events);
        $this->assertEquals($callback, $events[0]);
    }

    public function testGetAll()
    {
        $dispatcher = new EventDispatcher();
        $callback =  function () {};
        $dispatcher->on('octamp.test', $callback);

        $events = $dispatcher->getEvents();

        $this->assertIsArray($events);
        $this->assertCount(1, $events);
        $this->assertArrayHasKey('octamp.test', $events);
    }

    public function testOff()
    {
        $dispatcher = new EventDispatcher();
        $callback =  function () {};

        $dispatcher->off('octamp.test', $callback);
        $dispatcher->on('octamp.test', $callback);

        $events = $dispatcher->getEvents('octamp.test');
        $this->assertIsArray($events);
        $this->assertCount(1, $events);
        $this->assertEquals($callback, $events[0]);

        $dispatcher->off('octamp.test', $callback);

        $events = $dispatcher->getEvents('octamp.test');
        $this->assertIsArray($events);
        $this->assertCount(0, $events);
    }

    public function testEmit()
    {
        $dispatcher = new EventDispatcher();
        $result = 0;
        // Test emit without any events, should not do anything
        $dispatcher->emit('octamp.sum', 1, 1);
        $this->assertEquals(0, $result);
        $dispatcher->on('octamp.sum', function ($num1, $num2) use (&$result) {
            $result = $num1 + $num2;
        });

        $dispatcher->emit('octamp.sum', 1, 1);
        $this->assertEquals(2, $result);
    }
}
