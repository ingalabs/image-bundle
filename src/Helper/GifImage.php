<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Helper;

/**
 * GifImage.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class GifImage
{
    const MIME_TYPE = 'image/gif';

    /**
     * @var string
     */
    private $content;

    /**
     * Constructor.
     *
     * @param string $content
     */
    public function __construct($content = '')
    {
        $this->content = $content;
    }

    /**
     * Set content.
     *
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get mime.
     *
     * @return string
     */
    public function mime()
    {
        return static::MIME_TYPE;
    }

    /**
     * String conversion.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getContent();
    }
}
