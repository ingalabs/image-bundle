<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\Helper;

use IngaLabs\Bundle\ImageBundle\Helper\GifImage;

/**
 * GifImageTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class GifImageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var GifImage
     */
    protected $gifImage;

    protected function setUpGifImage($content = null)
    {
        if (null === $content) {
            $this->gifImage = new GifImage();
        } else {
            $this->gifImage = new GifImage($content);
        }
    }

    public function testNoContent()
    {
        $this->setUpGifImage();

        $this->assertSame('', $this->gifImage->getContent());
    }

    public function testContentInConstructor()
    {
        $this->setUpGifImage('foo');

        $this->assertSame('foo', $this->gifImage->getContent());
    }

    public function testContentInSetter()
    {
        $this->setUpGifImage();
        $this->gifImage->setContent('foo');

        $this->assertSame('foo', $this->gifImage->getContent());
    }

    public function testContentOverwrittenInSetter()
    {
        $this->setUpGifImage('foo');
        $this->gifImage->setContent('bar');

        $this->assertSame('bar', $this->gifImage->getContent());
    }

    public function testMime()
    {
        $this->setUpGifImage();

        $this->assertSame(GifImage::MIME_TYPE, $this->gifImage->mime());
    }

    public function testToString()
    {
        $this->setUpGifImage('foo_bar');

        $this->assertSame('foo_bar', (string) $this->gifImage);
    }
}
