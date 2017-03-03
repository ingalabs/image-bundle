<?php

namespace IngaLabs\Bundle\ImageBundle\Tests\Model;

use IngaLabs\Bundle\ImageBundle\Model\Aspect;

class AspectTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Aspect
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new Aspect();
    }

    public function testIdIsNull()
    {
        $this->assertNull($this->object->getId());
    }

    public function testId()
    {
        $reflectionClass = new \ReflectionClass(Aspect::class);

        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->object, 150);

        $this->assertEquals(150, $this->object->getId());
    }

    /**
     * @dataProvider propertiesProvider
     */
    public function testGettersAndSetters($property, $value)
    {
        $this->object->{'set'.ucfirst($property)}($value);
        if (method_exists($this->object, 'get'.ucfirst($property))) {
            $this->assertEquals($value, $this->object->{'get'.ucfirst($property)}());
        } else {
            $this->assertEquals($value, $this->object->{'is'.ucfirst($property)}());
        }
    }

    public function propertiesProvider()
    {
        return [
            ['shortName', 'foo_bar'],
            ['width', 300],
            ['height', 200],
        ];
    }
}
