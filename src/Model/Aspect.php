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
class Aspect
{
    private $id;
    private $shortName;
    private $width;
    private $height;

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

    public function setWidth(?int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }
}
