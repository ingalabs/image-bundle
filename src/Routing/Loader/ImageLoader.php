<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Routing\Loader;

use IngaLabs\Bundle\ImageBundle\Exception\LoaderException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * ImageLoader.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageLoader extends Loader
{
    /**
     * @var bool
     */
    private $loaded = false;

    /**
     * @var array
     */
    private $options = [
        'prefix' => '/assets/images',
        'file_levels' => '2:8',
    ];

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, array_intersect_key($options, $this->options));
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        if (true === $this->loaded) {
            throw new LoaderException('Do not add the "ingalabs_image" loader twice');
        }

        $routes = new RouteCollection();

        // prepare a new route
        $fileLevels = explode(':', $this->options['file_levels']);
        $hashs = '';
        foreach ($fileLevels as $key => $level) {
            $hashs .= '/{hash'.$key.'}';
        }

        $path = rtrim($this->options['prefix'], '/').$hashs.'/{hash}_{size}_{aspect}.{type}';
        $defaults = [
            '_controller' => 'ingalabs_image.image_controller:showAction',
        ];
        $requirements = [
            'hash' => '[a-zA-Z0-9]{32}',
            'size' => '[a-zA-Z0-9]+',
            'aspect' => '[a-zA-Z0-9]+',
            'type' => '[a-zA-Z0-9]+',
        ];

        foreach ($fileLevels as $key => $level) {
            $requirements['hash'.$key] = '[a-zA-Z0-9]{'.$level.'}';
        }

        $route = new Route($path, $defaults, $requirements);

        $routeName = 'ingalabs_image_image';
        $routes->add($routeName, $route);

        $this->loaded = true;

        return $routes;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return 'ingalabs_image' === $type;
    }
}
