<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests;

use Doctrine\Common\Persistence\ManagerRegistry;
use IngaLabs\Bundle\ImageBundle\Exception\ImageNotFoundException;
use IngaLabs\Bundle\ImageBundle\Exception\InvalidArgumentException;
use IngaLabs\Bundle\ImageBundle\ImageManager;
use IngaLabs\Bundle\ImageBundle\Model\Aspect;
use IngaLabs\Bundle\ImageBundle\Model\Image;
use IngaLabs\Bundle\ImageBundle\Model\Size;
use IngaLabs\Bundle\ImageBundle\Repository\AspectRepositoryInterface;
use IngaLabs\Bundle\ImageBundle\Repository\ImageRepositoryInterface;
use IngaLabs\Bundle\ImageBundle\Repository\SizeRepositoryInterface;

/**
 * ImageManagerTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructorWithInvalidDriver()
    {
        $managerRegistry = $this->getManagerRegistryMock();
        $this->expectExceptionWrapper(InvalidArgumentException::class, '/^Only driver/');
        $imageManager = new ImageManager($managerRegistry, ['driver' => 'foo']);
    }

    public function testGetDirectoryAndNameFor()
    {
        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry, ['prefix' => '/images']);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getDirectoryAndNameFor');
        $method->setAccessible(true);

        $dn = $method->invokeArgs($imageManager, [$image]);
        $this->assertSame($dn['directory'], '/images/01/01234567');
        $this->assertSame($dn['name'], '01234567890123456789012345678901_or_or.jpg');

        $dn = $method->invokeArgs($imageManager, [$image, 'sm', '1x1']);
        $this->assertSame($dn['name'], '01234567890123456789012345678901_sm_1x1.jpg');
    }

    public function testGetUrlFor()
    {
        $date = new \DateTime('1986-07-23 23:40:40', new \DateTimeZone('Europe/Budapest'));
        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setLastModifiedAt($date);

        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry, ['prefix' => '/images']);

        $url = $imageManager->getUrlFor($image);
        $this->assertSame($url, '/images/01/01234567/01234567890123456789012345678901_or_or.jpg');

        $url = $imageManager->getUrlFor($image, 'sm', '1x1');
        $this->assertSame($url, '/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');

        $url = $imageManager->getUrlFor($image, 'sm', '1x1', true);
        $this->assertSame($url, '/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg?timestamp=522538840');
    }

    public function testGetImageByHash()
    {
        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        $managerRegistry = $this->getManagerRegistryMock([$image]);
        $imageManager = new ImageManager($managerRegistry);

        $gotImage = $imageManager->getImageByHash('01234567890123456789012345678901');
        $this->assertSame($image, $gotImage);

        $this->expectExceptionWrapper(ImageNotFoundException::class, '/^Image with hash/');
        $gotImage = $imageManager->getImageByHash('foo_bar_foo_bar_foo_bar_foo_bar_');
    }

    public function testGetAspects()
    {
        $datas = [
            ['or', null, null],
            ['1x1', 1, 1],
            ['16x9', 16, 9],
        ];

        $aspects = [];
        foreach ($datas as $data) {
            $aspect = new Aspect();
            $aspect
                ->setShortName($data[0])
                ->setWidth($data[1])
                ->setHeight($data[2]);

            $aspects[] = $aspect;
        }

        $managerRegistry = $this->getManagerRegistryMock([], $aspects);
        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $this->assertNull($aspects['or']);
        $this->assertSame($aspects['1x1'], 1);
        $this->assertSame($aspects['16x9'], 16 / 9);
    }

    public function testGetSizes()
    {
        $datas = [
            ['or', null],
            ['sm', 100],
        ];

        $sizes = [];
        foreach ($datas as $data) {
            $size = new Size();
            $size
                ->setShortName($data[0])
                ->setMaxSize($data[1]);

            $sizes[] = $size;
        }

        $managerRegistry = $this->getManagerRegistryMock([], [], $sizes);
        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $this->assertNull($sizes['or']);
        $this->assertSame($sizes['sm'], 100);
    }

    public function getManagerRegistryMock($imagesArray = [], $aspectsArray = [], $sizesArray = [])
    {
        $images = [];
        foreach ($imagesArray as $id => $image) {
            $images[] = [$id, $image];
        }

        $imagesByHash = [];
        foreach ($imagesArray as $image) {
            if ($image instanceof Image) {
                $imagesByHash[] = [$image->getHash(), $image];
            }
        }

        $aspects = [];
        foreach ($aspectsArray as $id => $aspect) {
            $aspects[] = [$id, $aspect];
        }

        $sizes = [];
        foreach ($sizesArray as $id => $size) {
            $sizes[] = [$id, $size];
        }

        $imageRepository = $this
            ->getMockBuilder(ImageRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageRepository
            ->method('find')
            ->will($this->returnValueMap($images));
        $imageRepository
            ->method('findOneByHash')
            ->will($this->returnValueMap($imagesByHash));

        $aspectRepository = $this
            ->getMockBuilder(AspectRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $aspectRepository
            ->method('find')
            ->will($this->returnValueMap($aspects));
        $aspectRepository
            ->method('findAll')
            ->will($this->returnValue($aspectsArray));

        $sizeRepository = $this
            ->getMockBuilder(SizeRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sizeRepository
            ->method('find')
            ->will($this->returnValueMap($sizes));
        $sizeRepository
            ->method('findAll')
            ->will($this->returnValue($sizesArray));

        $repositoryMap = [
            [Image::class, null, $imageRepository],
            [Aspect::class, null, $aspectRepository],
            [Size::class, null, $sizeRepository],
        ];

        $managerRegistry = $this
            ->getMockBuilder(ManagerRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $managerRegistry
            ->method('getRepository')
            ->will($this->returnValueMap($repositoryMap));

        return $managerRegistry;
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
