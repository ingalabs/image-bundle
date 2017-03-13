<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Repository;

use Doctrine\Common\Persistence\ObjectRepository;
use IngaLabs\Bundle\ImageBundle\Model\Image;

/**
 * ImageRepositoryTrait.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
trait ImageRepositoryTrait
{
    /**
     * Find one by hash.
     *
     * @param string $hash
     *
     * @return null|Image
     */
    public function findOneByHash($hash)
    {
        return $this->findOneBy([
            'hash' => $hash,
        ]);
    }

    /**
     * @see ObjectRepository::findOneBy
     */
    abstract public function findOneBy(array $criteria);
}
