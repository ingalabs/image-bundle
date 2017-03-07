<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\DependencyInjection\Compiler;

use IngaLabs\Bundle\ImageBundle\DependencyInjection\Compiler\ConfigPass;
use IngaLabs\Bundle\ImageBundle\ImageManager;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * ConfigPassTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ConfigPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ConfigPass());
    }

    public function testServiceCalls()
    {
        $this->setUpContainer();

        $this->expectExceptionWrapper('RuntimeException', '/^Either parameter/');
        $this->compile();
    }

    public function testORMServiceCalls()
    {
        $this->setUpContainer(['ingalabs_image.backend_type_orm' => true]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'ingalabs_image.image_manager',
            0,
            new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE)
        );
    }

    public function testMongodbServiceCalls()
    {
        $this->setUpContainer(['ingalabs_image.backend_type_mongodb' => true]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'ingalabs_image.image_manager',
            0,
            new Reference('doctrine_mongodb', ContainerInterface::NULL_ON_INVALID_REFERENCE)
        );
    }

    public function testConfigInjected()
    {
        $config = [
            'foo' => 'bar',
        ];

        $this->setUpContainer([
            'ingalabs_image.backend_type_orm' => true,
            'ingalabs_image.config' => $config,
        ]);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'ingalabs_image.image_manager',
            1,
            $config
        );
    }

    protected function setUpContainer($parameters = [])
    {
        foreach ($parameters as $parameter => $value) {
            $this->setParameter($parameter, $value);
        }

        $doctrineService = new Definition();
        $this->setDefinition('doctrine', $doctrineService);

        $doctrineMongodbService = new Definition();
        $this->setDefinition('doctrine_mongodb', $doctrineMongodbService);

        $imageManagerService = new Definition(ImageManager::class, [null, []]);
        $this->setDefinition('ingalabs_image.image_manager', $imageManagerService);

        return $imageManagerService;
    }

    protected function expectExceptionWrapper($exception, $regexp = null)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($exception);
            if (null !== $regexp) {
                $this->expectExceptionMessageRegExp($regexp);
            }
        } else {
            if (null !== $regexp) {
                $this->setExpectedExceptionRegExp($exception, $regexp);
            } else {
                $this->setExpectedException($exception);
            }
        }
    }
}
