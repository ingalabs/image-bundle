<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Model;

/**
 * Size.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class Size
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $shortName;

    /**
     * @var int
     */
    private $maxSize;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set shortName.
     *
     * @param string $shortName
     *
     * @return $this
     */
    public function setShortName($shortName)
    {
        $this->shortName = $shortName;

        return $this;
    }

    /**
     * Get shortName.
     *
     * @return string
     */
    public function getShortName()
    {
        return $this->shortName;
    }

    /**
     * Set maxSize.
     *
     * @param int $maxSize
     *
     * @return $this
     */
    public function setMaxSize($maxSize)
    {
        $this->maxSize = $maxSize;

        return $this;
    }

    /**
     * Get maxSize.
     *
     * @return int
     */
    public function getMaxSize()
    {
        return $this->maxSize;
    }
}
