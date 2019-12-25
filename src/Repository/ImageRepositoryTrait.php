<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Repository;

use IngaLabs\Bundle\ImageBundle\Model\Image;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
trait ImageRepositoryTrait
{
    public function findOneByHash(string $hash): ?Image
    {
        return $this->findOneBy([
            'hash' => $hash,
        ]);
    }
}
