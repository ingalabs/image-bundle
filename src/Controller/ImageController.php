<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * ImageController.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageController
{
    /**
     * IndexAction.
     *
     * The main controller.
     *
     * @return Response
     */
    public function indexAction()
    {
        return new Response('Ok');
    }
}
