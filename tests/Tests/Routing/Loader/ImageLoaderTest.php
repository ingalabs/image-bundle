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
use IngaLabs\Bundle\ImageBundle\Tests\ExceptionWrapperTestCaseTrait;
use PHPUnit\Framework\TestCase;

/**
 * ImageLoaderTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageLoaderTest extends TestCase
{
    use ExceptionWrapperTestCaseTrait;

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
        $this->assertSame($prefix.'/{hash0}/{hash1}/{hash}_{size}_{aspect}.{type}', $route->getPath());
        $this->assertSame('ingalabs_image.image_controller:showAction', $route->getDefault('_controller'));
        $reqirements = [
            'hash0' => '[a-zA-Z0-9]{2}',
            'hash1' => '[a-zA-Z0-9]{8}',
            'hash' => '[a-zA-Z0-9]{32}',
            'size' => '[a-zA-Z0-9]+',
            'aspect' => '[a-zA-Z0-9]+',
            'type' => '[a-zA-Z0-9]+',
        ];
        foreach ($reqirements as $param => $requirement) {
            $this->assertSame($requirement, $route->getRequirement($param));
        }
    }

    public function testLoadFileLevels()
    {
        $prefix = '/foo/bar';
        $loader = new ImageLoader(['prefix' => $prefix, 'file_levels' => '4:6:8']);
        $routeCollection = $loader->load('.', 'ingalabs_image');
        $this->assertInstanceOf('Symfony\Component\Routing\RouteCollection', $routeCollection);

        $route = $routeCollection->get('ingalabs_image_image');
        $this->assertSame(1, $routeCollection->count());
        $this->assertInstanceOf('Symfony\Component\Routing\Route', $route);
        $this->assertSame($prefix.'/{hash0}/{hash1}/{hash2}/{hash}_{size}_{aspect}.{type}', $route->getPath());
        $this->assertSame('ingalabs_image.image_controller:showAction', $route->getDefault('_controller'));
        $reqirements = [
            'hash0' => '[a-zA-Z0-9]{4}',
            'hash1' => '[a-zA-Z0-9]{6}',
            'hash2' => '[a-zA-Z0-9]{8}',
            'hash' => '[a-zA-Z0-9]{32}',
            'size' => '[a-zA-Z0-9]+',
            'aspect' => '[a-zA-Z0-9]+',
            'type' => '[a-zA-Z0-9]+',
        ];
        foreach ($reqirements as $param => $requirement) {
            $this->assertSame($requirement, $route->getRequirement($param));
        }
    }
}
