<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use IngaLabs\Bundle\ImageBundle\Exception\ImageNotFoundException;
use IngaLabs\Bundle\ImageBundle\Exception\InvalidArgumentException;
use IngaLabs\Bundle\ImageBundle\Helper\GifImage;
use IngaLabs\Bundle\ImageBundle\ImageManager;
use IngaLabs\Bundle\ImageBundle\Model\Aspect;
use IngaLabs\Bundle\ImageBundle\Model\Image;
use IngaLabs\Bundle\ImageBundle\Model\Size;
use IngaLabs\Bundle\ImageBundle\Repository\AspectRepositoryInterface;
use IngaLabs\Bundle\ImageBundle\Repository\ImageRepositoryInterface;
use IngaLabs\Bundle\ImageBundle\Repository\SizeRepositoryInterface;
use Intervention\Image\Image as InventionImage;
use Intervention\Image\ImageManager as InventionManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageManagerTest extends TestCase
{
    use ExceptionWrapperTestCaseTrait;

    protected function setUp()
    {
        gc_collect_cycles();
        gc_disable();
    }

    protected function tearDown()
    {
        gc_enable();
    }

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

    public function testGetDirectoryAndNameForFileLevels()
    {
        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry, ['prefix' => '/images', 'file_levels' => '4:6:8']);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getDirectoryAndNameFor');
        $method->setAccessible(true);

        $dn = $method->invokeArgs($imageManager, [$image]);
        $this->assertSame($dn['directory'], '/images/0123/012345/01234567');
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

    public function testCreateResponseFromPath()
    {
        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry);

        $response = $imageManager->createResponse(__DIR__.'/../Fixtures/source/blank.jpg');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(__DIR__.'/../Fixtures/source/blank.jpg', (string) $response->getFile());
    }

    public function testCreateResponseFromPathNotExists()
    {
        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry);

        $this->expectExceptionWrapper(FileNotFoundException::class);

        $response = $imageManager->createResponse(__DIR__.'/../Fixtures/source/foo.jpg');
    }

    public function testCreateResponseFromImage()
    {
        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $image = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank.jpg');

        $response = $imageManager->createResponse($image);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($image->mime(), $response->headers->get('Content-Type'));
    }

    public function testCreateResponseFromGif()
    {
        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $image = new GifImage(file_get_contents(__DIR__.'/../Fixtures/source/blank.gif'));

        $response = $imageManager->createResponse($image);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($image->mime(), $response->headers->get('Content-Type'));
    }

    public function testGenerateInvalidAspect()
    {
        $aspects = $this->getAspects();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects);
        $imageManager = new ImageManager($managerRegistry);

        $image = new Image();

        $this->expectExceptionWrapper(InvalidArgumentException::class, '/^Invalid aspect.*bar/');
        $imageManager->generate($image, 'foo', 'bar');
    }

    public function testGenerateInvalidSize()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry);

        $image = new Image();

        $this->expectExceptionWrapper(InvalidArgumentException::class, '/^Invalid size.*foo/');
        $imageManager->generate($image, 'foo', 'or');
    }

    public function testGenerateExistFile()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->generate($image, 'sm', '1x1');

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');
        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testGenerateNotExistFile()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $this->expectExceptionWrapper(ImageNotFoundException::class);
        $imageManager->generate($image, 'sm', '1x1');
    }

    public function testGenerateNotExistFileMocked()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
            'mock_image' => true,
        ]);

        $image = $imageManager->generate($image, 'sm', '1x1');
        $this->assertInstanceOf(InventionImage::class, $image);
        $this->assertSame(100, $image->getWidth());
        $this->assertSame(100, $image->getHeight());
        $this->assertFileNotExists(__DIR__.'/../Fixtures/web');
    }

    public function testHandleUpload()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $file = new UploadedFile(
            __DIR__.'/../Fixtures/source/source.jpg',
            'source.jpg',
            null,
            null,
            null,
            true
        );

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $managerRegistry
            ->expects($this->never())
            ->method('getManagerForClass');
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleUpload($file);
        $this->assertInstanceOf(Image::class, $image);

        $this->assertSame(1920, $image->getWidth());
        $this->assertSame(1200, $image->getHeight());

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testHandleUploadFlush()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $file = new UploadedFile(
            __DIR__.'/../Fixtures/source/source.jpg',
            'source.jpg',
            null,
            null,
            null,
            true
        );

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->once())
            ->method('persist');
        $objectManager
            ->expects($this->once())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $managerRegistry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleUpload($file, true);

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testHandleUploadOrientate()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank_orientate.jpg',
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $file = new UploadedFile(
            __DIR__.'/../Fixtures/source/source.jpg',
            'source.jpg',
            null,
            null,
            null,
            true
        );

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleUpload($file);
        $this->assertInstanceOf(Image::class, $image);

        $this->assertSame(1920, $image->getWidth());
        $this->assertSame(1200, $image->getHeight());

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testHandleUploadAnimated()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank_animated.gif',
            __DIR__.'/../Fixtures/source/source.gif'
        );

        $file = new UploadedFile(
            __DIR__.'/../Fixtures/source/source.gif',
            'source.gif',
            null,
            null,
            null,
            true
        );

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleUpload($file);
        $this->assertInstanceOf(Image::class, $image);

        $this->assertSame(500, $image->getWidth());
        $this->assertSame(312, $image->getHeight());
        $this->assertTrue($image->isAnimated());

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testHandleCopy()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $file = new File(
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $managerRegistry
            ->expects($this->never())
            ->method('getManagerForClass');
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleCopy($file);
        $this->assertInstanceOf(Image::class, $image);

        $this->assertSame(1920, $image->getWidth());
        $this->assertSame(1200, $image->getHeight());

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testHandleCopyJpeg()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/source/source.jpeg'
        );

        $file = new File(
            __DIR__.'/../Fixtures/source/source.jpeg'
        );

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleCopy($file);
        $this->assertInstanceOf(Image::class, $image);

        $this->assertSame(1920, $image->getWidth());
        $this->assertSame(1200, $image->getHeight());
        $this->assertSame('jpg', $image->getType());

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testHandleCopyFlush()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $file = new File(
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->once())
            ->method('persist');
        $objectManager
            ->expects($this->once())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $managerRegistry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleCopy($file, true);

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testHandleCopyOrientate()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank_orientate.jpg',
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $file = new File(
            __DIR__.'/../Fixtures/source/source.jpg'
        );

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleCopy($file);
        $this->assertInstanceOf(Image::class, $image);

        $this->assertSame(1920, $image->getWidth());
        $this->assertSame(1200, $image->getHeight());

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testHandleCopyAnimated()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        copy(
            __DIR__.'/../Fixtures/source/blank_animated.gif',
            __DIR__.'/../Fixtures/source/source.gif'
        );

        $file = new File(
            __DIR__.'/../Fixtures/source/source.gif'
        );

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $image = $imageManager->handleCopy($file);
        $this->assertInstanceOf(Image::class, $image);

        $this->assertSame(500, $image->getWidth());
        $this->assertSame(312, $image->getHeight());
        $this->assertTrue($image->isAnimated());

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testCloneImage()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('_1234567890123456789012345678901')
            ->setType('jpg');

        if (!file_exists(__DIR__.'/../Fixtures/web/images/_1/_1234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/_1/_1234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/_1/_1234567/_1234567890123456789012345678901_or_or.jpg'
        );

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $managerRegistry
            ->expects($this->never())
            ->method('getManagerForClass');

        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $newImage = $imageManager->cloneImage($image);
        $this->assertInstanceOf(Image::class, $newImage);
        $this->assertFileExists(__DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($newImage));

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($newImage);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testCloneImageFlush()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('_1234567890123456789012345678901')
            ->setType('jpg');

        if (!file_exists(__DIR__.'/../Fixtures/web/images/_1/_1234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/_1/_1234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/_1/_1234567/_1234567890123456789012345678901_or_or.jpg'
        );

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->once())
            ->method('persist');
        $objectManager
            ->expects($this->once())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);
        $managerRegistry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $newImage = $imageManager->cloneImage($image, true);
        $this->assertInstanceOf(Image::class, $newImage);
        $this->assertFileExists(__DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($newImage));

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($image);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);

        $path = __DIR__.'/../Fixtures/web'.$imageManager->getUrlFor($newImage);
        $toRemove = [
            \dirname($path),
            \dirname(\dirname($path)),
            \dirname(\dirname(\dirname($path))),
            \dirname(\dirname(\dirname(\dirname($path)))),
        ];
        unlink($path);
        rmdir($toRemove[0]);
        rmdir($toRemove[1]);
        rmdir($toRemove[2]);
        rmdir($toRemove[3]);
    }

    public function testResize()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);

        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('resize');
        $method->setAccessible(true);

        if (!file_exists(__DIR__.'/../Fixtures/web')) {
            mkdir(__DIR__.'/../Fixtures/web');
        }

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        $inventionImage = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank.jpg');

        $outImage = $method->invokeArgs($imageManager, [
            $inventionImage,
            $image,
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/blank.jpg',
            'sm',
            '1x1',
        ]);

        $this->assertSame(__DIR__.'/../Fixtures/web/blank.jpg', $outImage);
        $this->assertFileExists($outImage);

        list($width, $height) = getimagesize($outImage);
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);

        unlink(__DIR__.'/../Fixtures/web/blank.jpg');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testResizeMock()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);

        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('resize');
        $method->setAccessible(true);

        if (file_exists(__DIR__.'/../Fixtures/web')) {
            rmdir(__DIR__.'/../Fixtures/web');
        }

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        $inventionImage = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank.jpg');

        $outImage = $method->invokeArgs($imageManager, [
            $inventionImage,
            $image,
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/blank.jpg',
            'sm',
            '1x1',
            true,
        ]);

        $this->assertFileNotExists(__DIR__.'/../Fixtures/web/blank.jpg');
        $this->assertFileNotExists(__DIR__.'/../Fixtures/web');
        $this->assertInstanceOf(InventionImage::class, $outImage);
    }

    public function testResizeOriginalSize()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);

        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('resize');
        $method->setAccessible(true);

        if (!file_exists(__DIR__.'/../Fixtures/web')) {
            mkdir(__DIR__.'/../Fixtures/web');
        }

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        $inventionImage = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank.jpg');

        $outImage = $method->invokeArgs($imageManager, [
            $inventionImage,
            $image,
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/blank.jpg',
            'or',
            '1x1',
        ]);

        $this->assertSame(__DIR__.'/../Fixtures/web/blank.jpg', $outImage);
        $this->assertFileExists($outImage);

        list($width, $height) = getimagesize($outImage);
        $this->assertSame(1200, $width);
        $this->assertSame(1200, $height);

        unlink(__DIR__.'/../Fixtures/web/blank.jpg');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testResizeOriginalAspect()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);

        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('resize');
        $method->setAccessible(true);

        if (!file_exists(__DIR__.'/../Fixtures/web')) {
            mkdir(__DIR__.'/../Fixtures/web');
        }

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        $inventionImage = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank.jpg');

        $outImage = $method->invokeArgs($imageManager, [
            $inventionImage,
            $image,
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/blank.jpg',
            'sm',
            'or',
        ]);

        $this->assertSame(__DIR__.'/../Fixtures/web/blank.jpg', $outImage);
        $this->assertFileExists($outImage);

        list($width, $height) = getimagesize($outImage);
        $this->assertSame(100, $width);

        unlink(__DIR__.'/../Fixtures/web/blank.jpg');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testResizeBigAspect()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);

        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('resize');
        $method->setAccessible(true);

        if (!file_exists(__DIR__.'/../Fixtures/web')) {
            mkdir(__DIR__.'/../Fixtures/web');
        }

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        $inventionImage = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank.jpg');

        $outImage = $method->invokeArgs($imageManager, [
            $inventionImage,
            $image,
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/blank.jpg',
            'sm',
            '5x1',
        ]);

        $this->assertSame(__DIR__.'/../Fixtures/web/blank.jpg', $outImage);
        $this->assertFileExists($outImage);

        list($width, $height) = getimagesize($outImage);
        $this->assertSame(100, $width);

        unlink(__DIR__.'/../Fixtures/web/blank.jpg');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testResizeBigAspectWithOriginalSize()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);

        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('resize');
        $method->setAccessible(true);

        if (!file_exists(__DIR__.'/../Fixtures/web')) {
            mkdir(__DIR__.'/../Fixtures/web');
        }

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        $inventionImage = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank.jpg');

        $outImage = $method->invokeArgs($imageManager, [
            $inventionImage,
            $image,
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/blank.jpg',
            'or',
            '5x1',
        ]);

        $this->assertSame(__DIR__.'/../Fixtures/web/blank.jpg', $outImage);
        $this->assertFileExists($outImage);

        list($width, $height) = getimagesize($outImage);
        $this->assertSame(1920, $width);

        unlink(__DIR__.'/../Fixtures/web/blank.jpg');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testResizeAnimated()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);

        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('resize');
        $method->setAccessible(true);

        if (!file_exists(__DIR__.'/../Fixtures/web')) {
            mkdir(__DIR__.'/../Fixtures/web');
        }

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('gif')
            ->setWidth(500)
            ->setHeight(312)
            ->setAnimated(true);

        $inventionImage = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank_animated.gif');

        $outImage = $method->invokeArgs($imageManager, [
            $inventionImage,
            $image,
            __DIR__.'/../Fixtures/source/blank_animated.gif',
            __DIR__.'/../Fixtures/web/blank_animated.gif',
            'sm',
            '1x1',
        ]);

        $this->assertSame(__DIR__.'/../Fixtures/web/blank_animated.gif', $outImage);
        $this->assertFileExists($outImage);

        list($width, $height) = getimagesize($outImage);
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);

        unlink(__DIR__.'/../Fixtures/web/blank_animated.gif');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testResizeAnimatedMock()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects, $sizes);

        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $inventionManager = new InventionManager([
            'driver' => 'gd',
        ]);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('resize');
        $method->setAccessible(true);

        if (file_exists(__DIR__.'/../Fixtures/web')) {
            rmdir(__DIR__.'/../Fixtures/web');
        }

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('gif')
            ->setWidth(500)
            ->setHeight(312)
            ->setAnimated(true);

        $inventionImage = $inventionManager
            ->make(__DIR__.'/../Fixtures/source/blank_animated.gif');

        $outImage = $method->invokeArgs($imageManager, [
            $inventionImage,
            $image,
            __DIR__.'/../Fixtures/source/blank_animated.gif',
            __DIR__.'/../Fixtures/web/blank_animated.gif',
            'sm',
            '1x1',
            true,
        ]);

        $this->assertFileNotExists(__DIR__.'/../Fixtures/web/blank_animated.gif');
        $this->assertFileNotExists(__DIR__.'/../Fixtures/web');
        $this->assertInstanceOf(GifImage::class, $outImage);
    }

    public function testCropImage()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->cropImage($image, 50, 50, 100, 100);

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);

        $this->assertSame(100, $image->getWidth());
        $this->assertSame(100, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testCropImageFlush()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg'
        );

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->once())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $managerRegistry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->cropImage($image, 50, 50, 100, 100, false, true);

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);

        $this->assertSame(100, $image->getWidth());
        $this->assertSame(100, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testCropImageGrayscale()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->cropImage($image, 50, 50, 100, 100, true);

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);

        $this->assertSame(100, $image->getWidth());
        $this->assertSame(100, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testCropImageAnimated()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('gif')
            ->setWidth(500)
            ->setHeight(312)
            ->setAnimated(true);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank_animated.gif',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->cropImage($image, 50, 50, 100, 100);

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);

        $this->assertSame(100, $image->getWidth());
        $this->assertSame(100, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testCropImageAnimatedGrayscale()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('gif')
            ->setWidth(500)
            ->setHeight(312)
            ->setAnimated(true);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank_animated.gif',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->cropImage($image, 50, 50, 100, 100, true);

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);

        $this->assertSame(100, $image->getWidth());
        $this->assertSame(100, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testRotate()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->rotate($image, 'left');

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        $this->assertSame(1200, $width);
        $this->assertSame(1920, $height);

        $this->assertSame(1200, $image->getWidth());
        $this->assertSame(1920, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testRotateRight()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->rotate($image, 'right');

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        $this->assertSame(1200, $width);
        $this->assertSame(1920, $height);

        $this->assertSame(1200, $image->getWidth());
        $this->assertSame(1920, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testRotateAnimated()
    {
        if ($this->skipOnTravis()) {
            $this->markTestSkipped();
        }

        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('gif')
            ->setWidth(500)
            ->setHeight(312)
            ->setAnimated(true);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank_animated.gif',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->rotate($image, 'left');

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        $this->assertSame(312, $width);
        $this->assertSame(500, $height);

        $this->assertSame(312, $image->getWidth());
        $this->assertSame(500, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testRotateAnimatedRight()
    {
        if ($this->skipOnTravis()) {
            $this->markTestSkipped();
        }

        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('gif')
            ->setWidth(500)
            ->setHeight(312)
            ->setAnimated(true);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank_animated.gif',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif'
        );

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->rotate($image, 'right');

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        $this->assertSame(312, $width);
        $this->assertSame(500, $height);

        $this->assertSame(312, $image->getWidth());
        $this->assertSame(500, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.gif');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testRotateFlush()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        copy(
            __DIR__.'/../Fixtures/source/blank.jpg',
            __DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg'
        );

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->once())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $managerRegistry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->rotate($image, 'left', true);

        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        list($width, $height) = getimagesize(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        $this->assertSame(1200, $width);
        $this->assertSame(1920, $height);

        $this->assertSame(1200, $image->getWidth());
        $this->assertSame(1920, $image->getHeight());

        $this->assertSame(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg', $imagePath);

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testRotateInvalidDirection()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setWidth(1920)
            ->setHeight(1200);

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $this->expectExceptionWrapper(InvalidArgumentException::class, '/"foo_bar" given/');
        $imageManager->rotate($image, 'foo_bar');
    }

    public function testDelete()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        touch(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        touch(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->never())
            ->method('remove');
        $objectManager
            ->expects($this->never())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $managerRegistry
            ->expects($this->never())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->delete($image);

        $this->assertFileNotExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');
        $this->assertFileNotExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');

        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testDeleteKeepOriginal()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        touch(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        touch(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->never())
            ->method('remove');
        $objectManager
            ->expects($this->never())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $managerRegistry
            ->expects($this->never())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->delete($image, true);

        $this->assertFileNotExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');
        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testDeleteKeepOriginalAndPurge()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        touch(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        touch(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->never())
            ->method('remove');
        $objectManager
            ->expects($this->never())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $managerRegistry
            ->expects($this->never())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->delete($image, true, true);

        $this->assertFileNotExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');
        $this->assertFileExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');

        unlink(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
    }

    public function testDeleteNotKeepOriginalAndPurge()
    {
        $aspects = $this->getAspects();
        $sizes = $this->getSizes();

        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        if (!file_exists(__DIR__.'/../Fixtures/web/images/01/01234567')) {
            mkdir(
                __DIR__.'/../Fixtures/web/images/01/01234567',
                0777,
                true
            );
        }

        touch(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');
        touch(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');

        $objectManager = $this
            ->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectManager
            ->expects($this->once())
            ->method('remove');
        $objectManager
            ->expects($this->once())
            ->method('flush');

        $managerRegistry = $this->getManagerRegistryMock([$image], $aspects, $sizes);
        $managerRegistry
            ->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $imageManager = new ImageManager($managerRegistry, [
            'prefix' => '/images',
            'image_dir' => __DIR__.'/../Fixtures/web',
        ]);

        $imagePath = $imageManager->delete($image, false, true);

        $this->assertFileNotExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg');
        $this->assertFileNotExists(__DIR__.'/../Fixtures/web/images/01/01234567/01234567890123456789012345678901_or_or.jpg');

        rmdir(__DIR__.'/../Fixtures/web/images/01/01234567');
        rmdir(__DIR__.'/../Fixtures/web/images/01');
        rmdir(__DIR__.'/../Fixtures/web/images');
        rmdir(__DIR__.'/../Fixtures/web');
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
        $aspects = $this->getAspects();

        $managerRegistry = $this->getManagerRegistryMock([], $aspects);
        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getAspects');
        $method->setAccessible(true);

        $aspects = $method->invokeArgs($imageManager, []);

        $this->assertNull($aspects['or']);
        $this->assertSame($aspects['1x1'], 1);
        $this->assertSame($aspects['5x1'], 5);
    }

    public function testGetSizes()
    {
        $sizes = $this->getSizes();

        $managerRegistry = $this->getManagerRegistryMock([], [], $sizes);
        $imageManager = new ImageManager($managerRegistry);

        $reflector = new \ReflectionClass(ImageManager::class);
        $method = $reflector->getMethod('getSizes');
        $method->setAccessible(true);

        $sizes = $method->invokeArgs($imageManager, []);

        $this->assertNull($sizes['or']);
        $this->assertSame($sizes['sm'], 100);
    }

    public function getSizes()
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

        return $sizes;
    }

    public function getAspects()
    {
        $datas = [
            ['or', null, null],
            ['1x1', 1, 1],
            ['5x1', 5, 1],
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

        return $aspects;
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
            ->willReturnMap($images);
        $imageRepository
            ->method('findOneByHash')
            ->willReturnMap($imagesByHash);

        $aspectRepository = $this
            ->getMockBuilder(AspectRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $aspectRepository
            ->method('find')
            ->willReturnMap($aspects);
        $aspectRepository
            ->method('findAll')
            ->willReturn($aspectsArray);

        $sizeRepository = $this
            ->getMockBuilder(SizeRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sizeRepository
            ->method('find')
            ->willReturnMap($sizes);
        $sizeRepository
            ->method('findAll')
            ->willReturn($sizesArray);

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
            ->willReturnMap($repositoryMap);

        return $managerRegistry;
    }

    private function skipOnTravis()
    {
        return '5.5' === getenv('TRAVIS_PHP_VERSION') || '5.6' === getenv('TRAVIS_PHP_VERSION') || 'hhvm' === getenv('TRAVIS_PHP_VERSION');
    }
}
