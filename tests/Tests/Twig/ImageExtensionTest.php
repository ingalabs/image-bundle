<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\Twig;

use Doctrine\Common\Persistence\ManagerRegistry;
use IngaLabs\Bundle\ImageBundle\ImageManager;
use IngaLabs\Bundle\ImageBundle\Model\Image;
use IngaLabs\Bundle\ImageBundle\Twig\ImageExtension;

/**
 * ImageExtensionTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testImage()
    {
        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg');

        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry, ['prefix' => '/images']);

        $loader = new \Twig_Loader_Array([
            'index.html' => '{{ image(image) }}',
        ]);
        $twig = new \Twig_Environment($loader, ['debug' => false, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0]);
        $twig->addExtension(new ImageExtension($imageManager));

        $this->assertSame('/images/01/01234567/01234567890123456789012345678901_or_or.jpg', $twig->render('index.html', ['image' => $image]));
    }

    public function testImageFull()
    {
        $image = new Image();
        $image
            ->setHash('01234567890123456789012345678901')
            ->setType('jpg')
            ->setLastModifiedAt(new \DateTime('1986-07-23 23:40:40', new \DateTimeZone('Europe/Budapest')));

        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry, ['prefix' => '/images']);

        $loader = new \Twig_Loader_Array([
            'index.html' => '{{ image(image, {\'size\': \'sm\', \'aspect\': \'1x1\', \'show_last_modified\': true}) }}',
        ]);
        $twig = new \Twig_Environment($loader, ['debug' => false, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0]);
        $twig->addExtension(new ImageExtension($imageManager));

        $this->assertSame('/images/01/01234567/01234567890123456789012345678901_sm_1x1.jpg?timestamp=522538840', $twig->render('index.html', ['image' => $image]));
    }

    public function testImageNull()
    {
        $image = null;

        $managerRegistry = $this->getManagerRegistryMock();
        $imageManager = new ImageManager($managerRegistry, ['prefix' => '/images']);

        $loader = new \Twig_Loader_Array([
            'index.html' => '{{ image(image) }}',
        ]);
        $twig = new \Twig_Environment($loader, ['debug' => false, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0]);
        $twig->addExtension(new ImageExtension($imageManager));

        $this->assertSame('', $twig->render('index.html', ['image' => $image]));
    }

    public function getManagerRegistryMock()
    {
        $managerRegistry = $this
            ->getMockBuilder(ManagerRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $managerRegistry;
    }
}
