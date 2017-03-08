<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\Routing\Loader;

use IngaLabs\Bundle\ImageBundle\Exception\LoaderException;
use IngaLabs\Bundle\ImageBundle\Routing\Loader\ImageLoader;

/**
 * ImageLoaderTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testSupports()
    {
        $loader = new ImageLoader();

        $this->assertTrue($loader->supports('.', 'ingalabs_image'));
        $this->assertTrue($loader->supports('foo_bar', 'ingalabs_image'));
        $this->assertFalse($loader->supports('.', 'foo_bar'));
    }

    public function testLoadedTwice()
    {
        $loader = new ImageLoader();
        $routes = $loader->load('.', 'ingalabs_image');

        $this->expectExceptionWrapper(LoaderException::class);
        $routes = $loader->load('.', 'ingalabs_image');
    }

    public function testLoad()
    {
        $prefix = '/foo/bar';
        $loader = new ImageLoader(['prefix' => $prefix]);
        $routeCollection = $loader->load('.', 'ingalabs_image');
        $this->assertInstanceOf('Symfony\Component\Routing\RouteCollection', $routeCollection);

        $route = $routeCollection->get('ingalabs_image_image');
        $this->assertSame(1, $routeCollection->count());
        $this->assertInstanceOf('Symfony\Component\Routing\Route', $route);
        $this->assertSame($prefix.'/{hash2}/{hash}_{size}_{aspect}.{type}', $route->getPath());
        $this->assertSame('ingalabs_image.image_controller:showAction', $route->getDefault('_controller'));
        $reqirements = [
            'hash2' => '[a-zA-Z0-9]{2}',
            'hash' => '[a-zA-Z0-9]{32}',
            'size' => '[a-zA-Z0-9]+',
            'aspect' => '[a-zA-Z0-9]+',
            'type' => '[a-zA-Z0-9]+',
        ];
        foreach ($reqirements as $param => $requirement) {
            $this->assertSame($requirement, $route->getRequirement($param));
        }
    }

    protected function expectExceptionWrapper($exception, $regexp = null)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($exception);
            if (null !== $regexp) {
                $this->expectExceptionMessageRegExp($regexp);
            }
        } else {
            if (null !== $regexp) {
                $this->setExpectedExceptionRegExp($exception, $regexp);
            } else {
                $this->setExpectedException($exception);
            }
        }
    }
}
