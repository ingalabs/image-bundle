<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle;

use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use IngaLabs\Bundle\ImageBundle\Model\Aspect;
use IngaLabs\Bundle\ImageBundle\Model\Image;
use IngaLabs\Bundle\ImageBundle\Model\Size;
use Intervention\Image\ImageManager as InventionManager;

/**
 * ImageManager.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageManager
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var array
     */
    private $options = [
        'prefix' => '/assets/images',
        'driver' => 'gd',
        'image_dir' => '',
        'mock_image' => false,
    ];

    /**
     * @var null|Aspect[]
     */
    private $aspects;

    /**
     * @var null|Size[]
     */
    private $sizes;

    /**
     * @var null|Client
     */
    private $client;

    public function __construct(ObjectManager $objectManager, array $options = [])
    {
        $this->objectManager = $objectManager;
        $this->options = array_merge($this->options, $options);
        $this->imageManager = new InventionManager([
            'driver' => $this->options['driver'],
        ]);

        if ('gd' !== $this->options['driver']) {
            throw new InvalidArgumentException(sprintf('Only driver "gd" is supported. "%s" given.', $this->options['driver']));
        }
    }

    public function getUrlFor(Image $image, $size = 'or', $aspect = 'or', $showLastModifiedAt = false)
    {
        $dn = $this->getDirectoryAndNameFor($image, $size, $aspect);

        if ($showLastModified) {
            $timestamp = '0';
            if (null !== $image->getLastModifiedAt()) {
                $timestamp = $image->getLastModifiedAt()->getTimestamp();
            }

            return sprintf('%s/%s?timestamp=%s', $dn['directory'], $dn['name'], $timestamp);
        }

        return sprintf('%s/%s', $dn['directory'], $dn['name']);
    }

    public function getDirectoryAndNameFor(Image $image, $size = 'or', $aspect = 'or')
    {
        $name = $image->getHash();

        return [
            'directory' => sprintf('%s/%s/%s',
                $this->options['prefix'],
                substr($name, 0, 2),
                substr($name, 0, 16)
            ),
            'name' => sprintf('%s_%s_%s.%s',
                $name,
                $size,
                $aspect,
                $image->getType()
            ),
        ];
    }

    /**
     * Lazy loads aspects.
     *
     * @return Aspect[]
     */
    private function getAspects()
    {
        if (null === $this->aspects) {
            $aspects = $this->objectManager->getRepository(Aspect::class)->findAll();

            foreach ($aspects as $aspect) {
                $this->aspects[$aspect->getShortName()] = null === $aspect->getHeight() ? null : $aspect->getWidth() / $aspect->getHeight();
            }
        }

        return $this->aspects;
    }

    /**
     * Lazy loads sizes.
     *
     * @return Size[]
     */
    private function getSizes()
    {
        if (null === $this->sizes) {
            $sizes = $this->objectManager->getRepository(Size::class)->findAll();

            foreach ($sizes as $size) {
                $this->sizes[$size->getShortName()] = $size->getMaxSize();
            }
        }

        return $this->sizes;
    }

    /**
     * Lazy loads Client.
     *
     * @return Client
     */
    private function getClient()
    {
        if (null === $this->client) {
            $this->client = new Client();
        }

        return $this->client;
    }
}
