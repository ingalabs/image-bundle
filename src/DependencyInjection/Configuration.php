<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ingalabs_image');

        $rootNode
            ->children()
                ->enumNode('doctrine_driver')
                    ->values(['orm', 'mongodb'])
                    ->defaultValue('orm')
                ->end()
                ->scalarNode('image_dir')
                    ->defaultValue('%kernel.root_dir%/../web')
                ->end()
                ->scalarNode('prefix')
                    ->defaultValue('/assets/images')
                ->end()
                ->scalarNode('driver')
                    ->defaultValue('gd')
                ->end()
                ->booleanNode('mock_image')
                    ->defaultValue('%kernel.debug%')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
