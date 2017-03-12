<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Repository\ORM;

use Doctrine\ORM\EntityRepository;
use IngaLabs\Bundle\ImageBundle\Repository\AspectRepositoryInterface;

/**
 * AspectRepository.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class AspectRepository extends EntityRepository implements AspectRepositoryInterface
{
}
