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
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class Configuration implements ConfigurationInterface
{
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
                ->scalarNode('file_levels')
                    ->defaultValue('2:8')
                    ->validate()
                        ->ifTrue(function ($v) {
                            $n = explode(':', $v);
                            foreach ($n as $i) {
                                if ($i !== (string) (int) $i || $i < 1 || $i > 31) {
                                    return true;
                                }
                            }

                            return false;
                        })
                        ->thenInvalid('Correct form: xx or xx:xx or xx:xx:xx or...')
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
