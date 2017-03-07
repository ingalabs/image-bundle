<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Controller;

use IngaLabs\Bundle\ImageBundle\Exception\ImageExceptionInterface;
use IngaLabs\Bundle\ImageBundle\Helper\GifImage;
use IngaLabs\Bundle\ImageBundle\ImageManager;
use Intervention\Image\Image as InventionImage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ImageController.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageController
{
    /**
     * @var ImageManager
     */
    private $imageManager;

    /**
     * Constructor.
     *
     * @param ImageManager $imageManager
     */
    public function __construct(ImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    /**
     * ShowAction.
     *
     * The main controller.
     *
     * @return Response
     */
    public function showAction($hash, $size, $aspect)
    {
        try {
            $image = $this->imageManager->getImageByHash($hash);
            $image = $this->imageManager->generate($image, $size, $aspect);
        } catch (ImageExceptionInterface $e) {
            throw new NotFoundHttpException(sprintf('Not found: %s', $e->getMessage()), $e);
        }

        return $this->imageManager->createResponse($image);
    }
}
