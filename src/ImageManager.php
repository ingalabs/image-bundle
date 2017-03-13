<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle;

use Doctrine\Common\Persistence\ManagerRegistry;
use GifCreator\GifCreator;
use GifFrameExtractor\GifFrameExtractor;
use IngaLabs\Bundle\ImageBundle\Exception\ImageNotFoundException;
use IngaLabs\Bundle\ImageBundle\Exception\InvalidArgumentException;
use IngaLabs\Bundle\ImageBundle\Helper\GifImage;
use IngaLabs\Bundle\ImageBundle\Model\Aspect;
use IngaLabs\Bundle\ImageBundle\Model\Image;
use IngaLabs\Bundle\ImageBundle\Model\Size;
use Intervention\Image\Image as InventionImage;
use Intervention\Image\ImageManager as InventionManager;
use Symfony\Component\Filesystem\Exception\IOException as FilesystemIOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * ImageManager.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageManager
{
    const DEFAULT_QUALITY = 90;

    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    /**
     * @var array
     */
    private $options = [
        'prefix' => '/assets/images',
        'driver' => 'gd',
        'image_dir' => '',
        'mock_image' => false,
        'file_levels' => '2:8',
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
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var InventionManager
     */
    private $imageManager;

    /**
     * Constructor.
     *
     * @param ManagerRegistry $managerRegistry
     * @param array           $options
     *
     * @throws InvalidArgumentException
     */
    public function __construct(ManagerRegistry $managerRegistry, array $options = [])
    {
        $this->managerRegistry = $managerRegistry;
        $this->options = array_merge($this->options, array_intersect_key($options, $this->options));
        if ('gd' !== $this->options['driver']) {
            throw new InvalidArgumentException(sprintf('Only driver "gd" is supported. "%s" given.', $this->options['driver']));
        }

        $this->imageManager = new InventionManager([
            'driver' => $this->options['driver'],
        ]);
        $this->filesystem = new Filesystem();
    }

    /**
     * Get URL for image.
     *
     * @param Image  $image
     * @param string $size
     * @param string $aspect
     * @param bool   $showLastModifiedAt
     *
     * @return string
     */
    public function getUrlFor(Image $image, $size = 'or', $aspect = 'or', $showLastModifiedAt = false)
    {
        $dn = $this->getDirectoryAndNameFor($image, $size, $aspect);

        if ($showLastModifiedAt) {
            $timestamp = '0';
            if (null !== $image->getLastModifiedAt()) {
                $timestamp = $image->getLastModifiedAt()->getTimestamp();
            }

            return sprintf('%s/%s?timestamp=%s', $dn['directory'], $dn['name'], $timestamp);
        }

        return sprintf('%s/%s', $dn['directory'], $dn['name']);
    }

    /**
     * Get directory and name for image.
     *
     * @param Image  $image
     * @param string $size
     * @param string $aspect
     *
     * @return array With key direcotry and name
     */
    private function getDirectoryAndNameFor(Image $image, $size = 'or', $aspect = 'or')
    {
        $name = $image->getHash();

        $fileLevels = explode(':', $this->options['file_levels']);
        $hashs = '';
        foreach ($fileLevels as $level) {
            $hashs .= '/'.substr($name, 0, $level);
        }

        return [
            'directory' => sprintf('%s%s',
                $this->options['prefix'],
                $hashs
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
     * Create response.
     *
     * @param InventionImage|GifImage|string $image
     *
     * @return Response
     */
    public function createResponse($image)
    {
        if ($image instanceof InventionImage || $image instanceof GifImage) {
            $response = new Response($image, Response::HTTP_OK, [
                'Content-Type' => $image->mime(),
            ]);

            return $response;
        }

        return new BinaryFileResponse($image);
    }

    /**
     * Main entrypoint.
     *
     * @param Image  $image
     * @param string $size
     * @param string $aspect
     *
     * @return InventionImage|GifImage|string
     *
     * @throws InvalidArgumentException
     * @throws ImageNotFoundException
     */
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
        if (!$this->filesystem->exists($originalFilename)) {
            if (true !== $this->options['mock_image']) {
                throw new ImageNotFoundException(sprintf('Image "%s" doesn\'t exists.', $originalFilename));
            }

            $originalFilename = __DIR__.'/Resources/images/blank'.($image->isAnimated() ? '_animated' : '').'.'.strtolower($image->getType());
            $isMock = true;
        }

        $img = $this->imageManager
            ->make($originalFilename)
            ->orientate();

        $img = $this->resize($img, $image, $originalFilename, $newFilename, $size, $aspect, $isMock);

        return $img instanceof InventionImage || $img instanceof GifImage ? $img : $newFilename;
    }

    /**
     * Handles an uploaded file.
     *
     * @param UploadedFile $file
     * @param bool         $flush
     *
     * @return Image
     */
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
                ->save($this->options['image_dir'].$this->getUrlFor($image), self::DEFAULT_QUALITY);
        }

        $image->setHeight($img->height());
        $image->setWidth($img->width());
        $image->setOriginalName($file->getClientOriginalName());
        $image->setCreatedAt(new \DateTime());
        $image->setLastModifiedAt(new \DateTime());

        if ($flush) {
            $om = $this->managerRegistry->getManagerForClass(Image::class);

            $om->persist($image);
            $om->flush();
        }

        return $image;
    }

    /**
     * Handle an upload from server.
     *
     * @param File $file
     * @param bool $flush
     *
     * @return Image
     */
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
            $om = $this->managerRegistry->getManagerForClass(Image::class);

            $om->persist($image);
            $om->flush();
        }

        return $image;
    }

    /**
     * Clones an image.
     *
     * @param Image $originalImage
     * @param bool  $flush
     *
     * @return Image
     */
    public function cloneImage(Image $originalImage, $flush = false)
    {
        $image = clone $originalImage;

        $hash = md5(uniqid());
        $image->setHash($hash);

        $oldFilename = $this->options['image_dir'].$this->getUrlFor($originalImage);
        $newFilename = $this->options['image_dir'].$this->getUrlFor($image);
        $this->filesystem->copy($oldFilename, $newFilename);

        if ($flush) {
            $om = $this->managerRegistry->getManagerForClass(Image::class);

            $om->persist($image);
            $om->flush();
        }

        return $image;
    }

    /**
     * Resize image.
     *
     * @param InventionImage $image
     * @param Image          $originalImage
     * @param string         $originalFilename
     * @param string         $newFilename
     * @param string         $size
     * @param string         $aspectString
     * @param bool           $isMock
     *
     * @return InventionImage|GifImage|string
     */
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
                $this->filesystem->dumpFile($newFilename, $gc->getGif());
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

    /**
     * Crom an image.
     *
     * @param Image $image
     * @param int   $x
     * @param int   $y
     * @param int   $width
     * @param int   $height
     * @param bool  $greyscale
     * @param bool  $flush
     *
     * @return string
     */
    public function cropImage(Image $image, $x, $y, $width, $height, $greyscale = false, $flush = false)
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

            $this->filesystem->dumpFile($originalFilename, $gc->getGif());

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

        if ($flush) {
            $om = $this->managerRegistry->getManagerForClass(Image::class);

            $om->flush();
        }

        return $originalFilename;
    }

    /**
     * Rotate image.
     *
     * @param Image  $image
     * @param string $direction left or right
     * @param bool   $flush
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function rotate(Image $image, $direction = 'right', $flush = false)
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

            $this->filesystem->dumpFile($originalFilename, $gc->getGif());

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

        if ($flush) {
            $om = $this->managerRegistry->getManagerForClass(Image::class);

            $om->flush();
        }

        return $originalFilename;
    }

    /**
     * Delete an image.
     *
     * @param Image $image
     * @param bool  $keepOriginal
     * @param bool  $purge
     *
     * @return $this
     */
    public function delete(Image $image, $keepOriginal = false, $purge = false)
    {
        // delete files
        foreach ($this->getSizes() as $size => $sizeVal) {
            foreach ($this->getAspects() as $aspect => $aspectVal) {
                if (!$keepOriginal || 'or' !== $size || 'or' !== $aspect) {
                    try {
                        $this->filesystem->remove($this->options['image_dir'].$this->getUrlFor($image, $size, $aspect));
                    } catch (FilesystemIOException $e) {
                        // Do nothing
                    }
                }
            }
        }

        if (!$keepOriginal && $purge) {
            $om = $this->managerRegistry->getManagerForClass(Image::class);

            $om->remove($image);
            $om->flush();
        }

        return $this;
    }

    /**
     * Get image by hash.
     *
     * @param string $hash
     *
     * @return Image|null
     */
    public function getImageByHash($hash)
    {
        $image = $this->managerRegistry->getRepository(Image::class)->findOneByHash($hash);

        if (null === $image) {
            throw new ImageNotFoundException(sprintf('Image with hash "%s" not found.', $hash));
        }

        return $image;
    }

    /**
     * Lazy loads aspects.
     *
     * @return Aspect[]
     */
    private function getAspects()
    {
        if (null === $this->aspects) {
            $aspects = $this->managerRegistry->getRepository(Aspect::class)->findAll();

            $this->aspects = [];
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
            $sizes = $this->managerRegistry->getRepository(Size::class)->findAll();

            $this->sizes = [];
            foreach ($sizes as $size) {
                $this->sizes[$size->getShortName()] = $size->getMaxSize();
            }
        }

        return $this->sizes;
    }
}
