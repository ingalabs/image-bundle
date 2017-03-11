<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests;

use IngaLabs\Bundle\ImageBundle\DependencyInjection\Compiler\ConfigPass;
use IngaLabs\Bundle\ImageBundle\DependencyInjection\IngaLabsImageExtension;
use IngaLabs\Bundle\ImageBundle\IngaLabsImageBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * IngaLabsImageBundleTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class IngaLabsImageBundleTest extends \PHPUnit_Framework_TestCase
{
    public function testBuildCompilerPasses()
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

    public function testContainerExtension()
    {
        $bundle = new IngaLabsImageBundle();

        $this->assertInstanceOf(IngaLabsImageExtension::class, $bundle->getContainerExtension());
    }
}
