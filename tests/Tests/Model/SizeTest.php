<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\Model;

use IngaLabs\Bundle\ImageBundle\Model\Size;
use PHPUnit\Framework\TestCase;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class SizeTest extends TestCase
{
    protected $object;

    protected function setUp()
    {
        $this->object = new Size();
    }

    public function testIdIsNull()
    {
        $this->assertNull($this->object->getId());
    }

    public function testId()
    {
        $reflectionClass = new \ReflectionClass(Size::class);

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
            ['maxSize', 300],
        ];
    }
}
