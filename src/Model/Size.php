<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Model;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class Size
{
    private $id;
    private $shortName;
    private $maxSize;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setShortName(string $shortName): self
    {
        $this->shortName = $shortName;

        return $this;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setMaxSize(int $maxSize): self
    {
        $this->maxSize = $maxSize;

        return $this;
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }
}
