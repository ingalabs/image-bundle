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
use Doctrine\ORM\Version;
use IngaLabs\Bundle\ImageBundle\DependencyInjection\Compiler\ConfigPass;
use IngaLabs\Bundle\ImageBundle\DependencyInjection\IngaLabsImageExtension;
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

        if (class_exists(Version::class)) {
            $container->addCompilerPass(
                DoctrineOrmMappingsPass::createYamlMappingDriver(
                    $mappings,
                    [],
                    'ingalabs_image.backend_type_orm',
                    $aliases
            ));
        }

        if (class_exists(DoctrineMongoDBMappingsPass::class)) {
            $container->addCompilerPass(
                DoctrineMongoDBMappingsPass::createYamlMappingDriver(
                    $mappings,
                    [],
                    'ingalabs_image.backend_type_mongodb',
                    $aliases
            ));
        }

        $container->addCompilerPass(new ConfigPass());
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        return new IngaLabsImageExtension();
    }
}
