<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Repository\MongoDB;

use Doctrine\ODM\MongoDB\DocumentRepository;
use IngaLabs\Bundle\ImageBundle\Repository\AspectRepositoryInterface;

/**
 * AspectRepository.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class AspectRepository extends DocumentRepository implements AspectRepositoryInterface
{
}
