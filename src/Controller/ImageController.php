<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Controller;

use IngaLabs\Bundle\ImageBundle\ImageManager;
use Intervention\Image\Image as InventionImage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
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
        $image = $this->imageManager->getImageByHash($hash);

        try {
            $image = $this->imageManager->generate($image, $size, $aspect);
        } catch (\Exception $e) {
            throw new NotFoundHttpException('Not found: '.$e->getMessage(), $e);
        }

        if ($image instanceof InventionImage) {
            $response = new Response($image, Response::HTTP_OK, [
                'Content-Type' => $image->mime(),
            ]);

            return $response;
        }

        if (is_array($image) && array_key_exists('content', $image)) {
            $response = new Response($image['content'], Response::HTTP_OK, [
                'Content-Type' => 'image/gif',
            ]);

            return $response;
        }

        return new BinaryFileResponse($image);
    }
}
