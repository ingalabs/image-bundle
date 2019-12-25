<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\DependencyInjection;

use IngaLabs\Bundle\ImageBundle\DependencyInjection\IngaLabsImageExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class IngaLabsImageExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions()
    {
        return [
            new IngaLabsImageExtension(),
        ];
    }

    public function testParametersSet()
    {
        $this->load([]);

        $defaultConfig = [
            'image_dir' => '%kernel.root_dir%/../web',
            'prefix' => '/assets/images',
            'mock_image' => '%kernel.debug%',
            'file_levels' => '2:8',
        ];
        $this->assertContainerBuilderHasParameter('ingalabs_image.config', $defaultConfig);
    }
}
