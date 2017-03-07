<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle;

use Doctrine\Common\Persistence\ObjectManager;
use GifCreator\GifCreator;
use GifFrameExtractor\GifFrameExtractor;
use GuzzleHttp\Client;
use IngaLabs\Bundle\ImageBundle\Exception\ImageNotFoundException;
use IngaLabs\Bundle\ImageBundle\Exception\InvalidArgumentException;
use IngaLabs\Bundle\ImageBundle\Exception\IOException;
use IngaLabs\Bundle\ImageBundle\Helper\GifImage;
use IngaLabs\Bundle\ImageBundle\Model\Aspect;
use IngaLabs\Bundle\ImageBundle\Model\Image;
use IngaLabs\Bundle\ImageBundle\Model\Size;
use Intervention\Image\ImageManager as InventionManager;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

    /**
     * Constructor.
     *
     * @param ObjectManager $objectManager
     * @param array         $options
     *
     * @throws InvalidArgumentException
     */
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

    private function getDirectoryAndNameFor(Image $image, $size = 'or', $aspect = 'or')
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

    public function generate(Image $image, $size, $aspect)
    {
        if (!array_key_exists($aspect, $this->getAspects())) {
            throw new InvalidArgumentException(sprintf(
                'Invalid aspect. "%s" given. Valid values: %s.',
                $aspect,
                implode(', ', array_keys($this->getAspects()))
            ));
        }

        if (!array_key_exists($size, $this->getSizes())) {
            throw new InvalidArgumentException(sprintf(
                'Invalid size. "%s" given. Valid values: %s.',
                $size,
                implode(', ', array_keys($this->getSizes()))
            ));
        }

        $originalFilename = $this->options['image_dir'].$this->getUrlFor($image);
        $newFilename = $this->options['image_dir'].$this->getUrlFor($image, $size, $aspect);

        $isMock = false;
        if (!file_exists($originalFilename)) {
            if (true !== $this->options['mock_image']) {
                throw new ImageNotFoundException(sprintf('Image "%s" doesn\'t exists.', $originalFilename));
            } elseif (false === $fileExists) {
                $originalFilename = __DIR__.'/Resources/images/blank'.($image->isAnimated() ? '_animated' : '').'.'.strtolower($image->getType());

                $isMock = true;
            }
        }

        if (!file_exists(dirname($newFilename))) {
            mkdir(dirname($newFilename), 0755, true);
        }

        $img = $this->imageManager
            ->make($originalFilename)
            ->orientate();

        $img = $this->resize($img, $image, $originalFilename, $newFilename, $size, $aspect, $isMock);

        return $img instanceof InventionImage || $img instanceof GifImage ? $img : $newFilename;
    }

    public function handleUpload(UploadedFile $file, $flush = false)
    {
        $image = new Image();

        $extension = strtolower($file->getClientOriginalExtension());
        $hash = md5(uniqid());
        $image->setHash($hash);
        $image->setType($extension);

        $dn = $this->getDirectoryAndNameFor($image);
        $file->move($this->options['image_dir'].$dn['directory'], $dn['name']);

        if ('gif' === $extension && GifFrameExtractor::isAnimatedGif($this->options['image_dir'].$this->getUrlFor($image))) {
            $image->setAnimated(true);
        }

        $img = $this->imageManager
            ->make($this->options['image_dir'].$this->getUrlFor($image));

        $orientation = $img->exif('Orientation');

        if (null !== $orientation && 1 !== $orientation) {
            $img = $img
                ->orientate()
                ->save($this->options['image_dir'].$this->getUrlFor($image), 90);
        }

        $image->setHeight($img->height());
        $image->setWidth($img->width());
        $image->setOriginalName($file->getClientOriginalName());
        $image->setCreatedAt(new \DateTime());
        $image->setLastModifiedAt(new \DateTime());

        if ($flush) {
            $em = $this->objectManager->getManagerForClass(Image::class);

            $em->persist($image);
            $em->flush();
        }

        return $image;
    }

    public function handleCopy(File $file, $flush = false)
    {
        $image = new Image();

        $extension = strtolower($file->getExtension());
        if ('jpeg' === $extension) {
            $extension = 'jpg';
        }
        $hash = md5(uniqid());
        $image->setHash($hash);
        $image->setType($extension);

        $dn = $this->getDirectoryAndNameFor($image);
        $file->move($this->options['image_dir'].$dn['directory'], $dn['name']);

        if ('gif' === $extension && GifFrameExtractor::isAnimatedGif($this->options['image_dir'].$this->getUrlFor($image))) {
            $image->setAnimated(true);
        }

        $img = $this->imageManager
            ->make($this->options['image_dir'].$this->getUrlFor($image));

        $orientation = $img->exif('Orientation');

        if (null !== $orientation && 1 !== $orientation) {
            $img = $img
                ->orientate()
                ->save($this->options['image_dir'].$this->getUrlFor($image), 90);
        }

        $image->setHeight($img->height());
        $image->setWidth($img->width());
        $image->setOriginalName($file->getFilename());
        $image->setCreatedAt(new \DateTime());
        $image->setLastModifiedAt(new \DateTime());

        if ($flush) {
            $em = $this->objectManager->getManagerForClass(Image::class);

            $em->persist($image);
            $em->flush();
        }

        return $image;
    }

    public function cloneImage(Image $originalImage, $flush = false)
    {
        $image = clone $originalImage;

        $hash = md5(uniqid());
        $image->setHash($hash);

        $oldFilename = $this->options['image_dir'].$this->getUrlFor($originalImage);
        $newFilename = $this->options['image_dir'].$this->getUrlFor($image);
        if (!file_exists(dirname($newFilename))) {
            mkdir(dirname($newFilename), 0755, true);
        }

        copy($oldFilename, $newFilename);

        if ($flush) {
            $em = $this->objectManager->getManagerForClass(Image::class);

            $em->persist($image);
            $em->flush();
        }

        return $image;
    }

    private function resize(InventionImage $image, Image $originalImage, $originalFilename, $newFilename, $size, $aspectString, $isMock = false)
    {
        $origWidth = $image->getWidth();
        $origHeight = $image->getHeight();
        $origAspect = $origWidth / $origHeight;

        // Calculate the aspect
        $aspect = $this->aspects[$aspectString];
        if (null === $aspect) {
            $aspect = $origAspect;
        }

        // Calculate the crop-box
        $x = $y = 0;
        if ($aspect > $origAspect) {
            // Keep the width
            $width = $origWidth;
            $height = $width / $aspect;
            $y = ($origHeight - $height) / 2;
        } else {
            // Keep the height
            $height = $origHeight;
            $width = $height * $aspect;
            $x = ($origWidth - $width) / 2;
        }

        // Calculate the width, height
        if ($aspect > 1) {
            if (null === $this->sizes[$size]) {
                $this->sizes[$size] = $origWidth;
            }
            $resizeWidth = min($this->sizes[$size], $width);
            $resizeHeight = $resizeWidth / $aspect;
        } else {
            if (null === $this->sizes[$size]) {
                $this->sizes[$size] = $origHeight;
            }
            $resizeHeight = min($this->sizes[$size], $height);
            $resizeWidth = $resizeHeight * $aspect;
        }

        if ($originalImage->isAnimated()) {
            $gfe = new GifFrameExtractor();
            $gfe->extract($originalFilename);

            $images = $gfe->getFrameImages();
            $durations = $gfe->getFrameDurations();

            foreach ($images as &$frame) {
                $frameRes = $this->imageManager->make($frame);

                $frameRes = $frameRes->crop((int) $width, (int) $height, (int) $x, (int) $y);
                $frameRes = $frameRes->resize((int) $resizeWidth, (int) $resizeHeight);

                $frame = $frameRes->encode('gif')->getCore();
            }

            $gc = new GifCreator();
            $gc->create($images, $durations, 0);

            if (!$isMock) {
                if (false === @file_put_contents($newFilename, $gc->getGif())) {
                    throw new IOException(sprintf('Cannot write %s', $newFilename));
                }
            } else {
                return new GifImage($gc->getGif());
            }
        } else {
            $image = $image->crop((int) $width, (int) $height, (int) $x, (int) $y);
            $image = $image->resize((int) $resizeWidth, (int) $resizeHeight);

            if (!$isMock) {
                $image->save($newFilename, 90);
            } else {
                return $image->encode();
            }
        }

        return $newFilename;
    }

    public function cropImage(Image $image, $x, $y, $width, $height, $greyscale = false)
    {
        $originalFilename = $this->options['image_dir'].$this->getUrlFor($image);

        if ($image->isAnimated()) {
            $gfe = new GifFrameExtractor();
            $gfe->extract($originalFilename);

            $images = $gfe->getFrameImages();
            $durations = $gfe->getFrameDurations();

            foreach ($images as &$frame) {
                $frameRes = $this->imageManager->make($frame);

                $frameRes = $frameRes->crop((int) $width, (int) $height, (int) $x, (int) $y);

                if ($greyscale) {
                    $frameRes = $frameRes->greyscale();
                }

                $frame = $frameRes->encode('gif')->getCore();
            }

            $gc = new GifCreator();
            $gc->create($images, $durations, 0);

            if (false === @file_put_contents($originalFilename, $gc->getGif())) {
                throw new IOException(sprintf('Cannot write %s', $originalFilename));
            }

            $image
                ->setWidth((int) $width)
                ->setHeight((int) $height);

            $this->delete($image, true, false);
        } else {
            $img = $this->imageManager
                ->make($originalFilename)
                ->orientate();

            $img = $img->crop((int) $width, (int) $height, (int) $x, (int) $y);

            if ($greyscale) {
                $img = $img->greyscale();
            }

            $image
                ->setWidth((int) $width)
                ->setHeight((int) $height);

            $this->delete($image, true, false);

            $img->save($originalFilename, 90);
        }

        $image->setLastModifiedAt(new \DateTime());

        return $originalFilename;
    }

    public function rotate(Image $image, $direction = 'right')
    {
        if (!in_array($direction, ['left', 'right'], true)) {
            throw new InvalidArgumentException(sprintf('Argument 2 of %s has to be either left or right. "%s" given.', __METHOD__, $direction));
        }

        $originalFilename = $this->options['image_dir'].$this->getUrlFor($image);

        if ($image->isAnimated()) {
            $gfe = new GifFrameExtractor();
            $gfe->extract($originalFilename);

            $images = $gfe->getFrameImages();
            $durations = $gfe->getFrameDurations();

            foreach ($images as &$frame) {
                $img = $this->imageManager->make($frame);

                if ('right' === $direction) {
                    $img->rotate(270);
                } else {
                    $img->rotate(90);
                }

                $frame = $img->encode('gif')->getCore();
            }

            $gc = new GifCreator();
            $gc->create($images, $durations, 0);

            if (false === @file_put_contents($originalFilename, $gc->getGif())) {
                throw new IOException(sprintf('Cannot write %s', $originalFilename));
            }

            $this->delete($image, true, false);
        } else {
            $img = $this->imageManager
                ->make($originalFilename)
                ->orientate();

            if ('right' === $direction) {
                $img->rotate(270);
            } else {
                $img->rotate(90);
            }

            $this->delete($image, true, false);

            $img->save($originalFilename, 90);
        }

        $width = $image->getWidth();
        $height = $image->getHeight();
        $image->setHeight($width);
        $image->setWidth($height);
        $image->setLastModifiedAt(new \DateTime());

        return $this;
    }

    public function delete(Image $image, $keepOriginal = false, $purge = true)
    {
        // delete files
        foreach ($this->getSizes() as $size => $sizeVal) {
            foreach ($this->getAspects() as $aspect => $aspectVal) {
                if (!$keepOriginal || 'or' !== $size || 'or' !== $aspect) {
                    @unlink($this->options['image_dir'].$this->getUrlFor($image, $size, $aspect));
                }
            }
        }

        if (!$keepOriginal && $purge) {
            $em = $this->objectManager->getManagerForClass(Image::class);

            $em->remove($image);
            $em->flush();
        }
    }

    public function getImageByHash($hash)
    {
        return $this->objectManager->getManagerForClass(Image::class)->findOneByHash($hash);
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
