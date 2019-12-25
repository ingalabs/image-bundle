<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Twig;

use IngaLabs\Bundle\ImageBundle\ImageManager;
use IngaLabs\Bundle\ImageBundle\Model\Image;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageExtension extends AbstractExtension
{
    private $imageManager;

    public function __construct(ImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('image', [$this, 'getUrlFor']),
        ];
    }

    public function getUrlFor(Image $image = null, array $options = [])
    {
        if (null === $image) {
            return '';
        }

        $size = isset($options['size']) ? $options['size'] : 'or';
        $aspect = isset($options['aspect']) ? $options['aspect'] : 'or';
        $showLastModified = isset($options['show_last_modified']) ? $options['show_last_modified'] : false;

        return $this->imageManager->getUrlFor($image, $size, $aspect, $showLastModified);
    }
}
