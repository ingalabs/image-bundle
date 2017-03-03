<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\Bundle\MongoDBBundle\DependencyInjection\Compiler\DoctrineMongoDBMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * IngaLabsImageBundle.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class IngaLabsImageBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $modelDir = realpath(__DIR__.'/Resources/config/doctrine-entities');
        $mappings = [
            $modelDir => Model::class,
        ];
        $aliases = [
            $this->getName() => Model::class,
        ];

        if (class_exists('Doctrine\\ORM\\Version')) {
            $container->addCompilerPass(
                DoctrineOrmMappingsPass::createYamlMappingDriver(
                    $mappings,
                    [],
                    false,
                    $aliases
            ));
        }

        if (class_exists(DoctrineMongoDBMappingsPass::class)) {
            $container->addCompilerPass(
                DoctrineMongoDBMappingsPass::createYamlMappingDriver(
                    $mappings,
                    [],
                    false,
                    $aliases
            ));
        }
    }
}
