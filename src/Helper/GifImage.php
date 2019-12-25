<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Helper;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class GifImage
{
    const MIME_TYPE = 'image/gif';

    private $content;

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function mime(): string
    {
        return static::MIME_TYPE;
    }

    public function __toString(): string
    {
        return $this->getContent();
    }
}
