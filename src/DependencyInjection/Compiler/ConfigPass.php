<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * ConfigPass.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ConfigPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('ingalabs_image.image_manager')
            || !$container->hasDefinition('ingalabs_image.routing_loader')
        ) {
            return;
        }

        if ($container->hasParameter('ingalabs_image.backend_type_orm')) {
            $doctrine = 'doctrine';
        } elseif ($container->hasParameter('ingalabs_image.backend_type_mongodb')) {
            $doctrine = 'doctrine_mongodb';
        } else {
            throw new \RuntimeException(
                'Either parameter "ingalabs_image.backend_type_orm" or '.
                '"ingalabs_image.backend_type_mongodb" should be set.');
        }

        $config = [];
        if ($container->hasParameter('ingalabs_image.config')) {
            $config = $container->getParameter('ingalabs_image.config');
        }

        $container
            ->findDefinition('ingalabs_image.image_manager')
            ->replaceArgument(0, new Reference($doctrine, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->replaceArgument(1, $config);

        $container
            ->findDefinition('ingalabs_image.routing_loader')
            ->replaceArgument(0, $config);
    }
}
