<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Controller;

use IngaLabs\Bundle\ImageBundle\Exception\ImageExceptionInterface;
use IngaLabs\Bundle\ImageBundle\ImageManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageController
{
    private $imageManager;

    public function __construct(ImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    public function showAction(string $hash, string $size, string $aspect): Response
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
