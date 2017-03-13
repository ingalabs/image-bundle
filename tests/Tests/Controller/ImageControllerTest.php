<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\Controller;

use IngaLabs\Bundle\ImageBundle\Controller\ImageController;
use IngaLabs\Bundle\ImageBundle\Exception\ImageNotFoundException;
use IngaLabs\Bundle\ImageBundle\Exception\InvalidArgumentException;
use IngaLabs\Bundle\ImageBundle\ImageManager;
use IngaLabs\Bundle\ImageBundle\Model\Image;
use IngaLabs\Bundle\ImageBundle\Tests\ExceptionWrapperTestCaseTrait;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ImageControllerTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageControllerTest extends \PHPUnit_Framework_TestCase
{
    use ExceptionWrapperTestCaseTrait;

    public function testCreateResponseCalled()
    {
        $image = new Image();

        $imageManager = $this
            ->getMockBuilder(ImageManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageManager
            ->expects($this->once())
            ->method('getImageByHash')
            ->will($this->returnValue($image));
        $imageManager
            ->expects($this->once())
            ->method('generate');
        $imageManager
            ->expects($this->once())
            ->method('createResponse');

        $controller = new ImageController($imageManager);
        $response = $controller->showAction('01234567890123456789012345678901', 'or', 'or');
    }

    public function testNotFoundImage()
    {
        $imageManager = $this
            ->getMockBuilder(ImageManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageManager
            ->expects($this->once())
            ->method('getImageByHash')
            ->will($this->throwException(new ImageNotFoundException()));
        $imageManager
            ->expects($this->never())
            ->method('generate');
        $imageManager
            ->expects($this->never())
            ->method('createResponse');

        $controller = new ImageController($imageManager);

        $this->expectExceptionWrapper(NotFoundHttpException::class);
        $controller->showAction('01234567890123456789012345678901', 'or', 'or');
    }

    public function testGenerateInvalid()
    {
        $image = new Image();

        $imageManager = $this
            ->getMockBuilder(ImageManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $imageManager
            ->expects($this->once())
            ->method('getImageByHash')
            ->will($this->returnValue($image));
        $imageManager
            ->expects($this->once())
            ->method('generate')
            ->will($this->throwException(new InvalidArgumentException()));
        $imageManager
            ->expects($this->never())
            ->method('createResponse');

        $controller = new ImageController($imageManager);

        $this->expectExceptionWrapper(NotFoundHttpException::class);
        $controller->showAction('01234567890123456789012345678901', 'or', 'or');
    }
}
