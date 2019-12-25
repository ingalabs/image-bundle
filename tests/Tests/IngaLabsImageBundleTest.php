<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\Bundle\MongoDBBundle\DependencyInjection\Compiler\DoctrineMongoDBMappingsPass;
use Doctrine\ORM\Version;
use IngaLabs\Bundle\ImageBundle\DependencyInjection\Compiler\ConfigPass;
use IngaLabs\Bundle\ImageBundle\DependencyInjection\IngaLabsImageExtension;
use IngaLabs\Bundle\ImageBundle\IngaLabsImageBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * IngaLabsImageBundleTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class IngaLabsImageBundleTest extends TestCase
{
    public function testConfigCompilerPass()
    {
        $container = new ContainerBuilder();
        $bundle = new IngaLabsImageBundle();
        $bundle->build($container);

        $config = $container->getCompilerPassConfig();
        $passes = $config->getBeforeOptimizationPasses();

        $foundConfigPass = false;

        foreach ($passes as $pass) {
            if ($pass instanceof ConfigPass) {
                $foundConfigPass = true;
            }
        }

        $this->assertTrue($foundConfigPass);
    }

    public function testORMCompilerPass()
    {
        if (!class_exists(Version::class)) {
            $this->markTestSkipped();
        }

        $container = new ContainerBuilder();
        $container->setParameter('ingalabs_image.backend_type_orm', true);
        $bundle = new IngaLabsImageBundle();
        $bundle->build($container);

        $config = $container->getCompilerPassConfig();
        $passes = $config->getBeforeOptimizationPasses();

        $foundORMPass = false;

        foreach ($passes as $pass) {
            if ($pass instanceof DoctrineOrmMappingsPass) {
                $foundORMPass = true;
            }
        }

        $this->assertTrue($foundORMPass);
    }

    public function testMongoDBCompilerPass()
    {
        if (!class_exists(DoctrineMongoDBMappingsPass::class)) {
            $this->markTestSkipped();
        }

        $container = new ContainerBuilder();
        $container->setParameter('ingalabs_image.backend_type_mongodb', true);
        $bundle = new IngaLabsImageBundle();
        $bundle->build($container);

        $config = $container->getCompilerPassConfig();
        $passes = $config->getBeforeOptimizationPasses();

        $foundMongoDBPass = false;

        foreach ($passes as $pass) {
            if ($pass instanceof DoctrineMongoDBMappingsPass) {
                $foundMongoDBPass = true;
            }
        }

        $this->assertTrue($foundMongoDBPass);
    }

    public function testContainerExtension()
    {
        $bundle = new IngaLabsImageBundle();

        $this->assertInstanceOf(IngaLabsImageExtension::class, $bundle->getContainerExtension());
    }
}
