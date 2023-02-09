<?php

namespace Octamp\Client\Tests\Unit\Roles;

trait RoleTrait
{
    public function testGetFeatures()
    {
        $role = new ($this->getRoleClass())();
        $features = $role->getFeatures();
        $this->assertInstanceOf(\stdClass::class, $features);
    }

    /**
     * @dataProvider handlesMessageProvider
     */
    public function testHandlesMessage($message, $expected)
    {
        $role = new ($this->getRoleClass())();
        $result = $role->handlesMessage($message);
        $this->assertSame($expected, $result);
    }

    abstract public static function handlesMessageProvider();

    abstract public function getRoleClass();
}
