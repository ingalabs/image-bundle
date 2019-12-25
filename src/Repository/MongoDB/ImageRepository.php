<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Repository\MongoDB;

use Doctrine\ODM\MongoDB\DocumentRepository;
use IngaLabs\Bundle\ImageBundle\Repository\ImageRepositoryInterface;
use IngaLabs\Bundle\ImageBundle\Repository\ImageRepositoryTrait;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageRepository extends DocumentRepository implements ImageRepositoryInterface
{
    use ImageRepositoryTrait;
}
