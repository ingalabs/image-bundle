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
            'doctrine_driver' => 'orm',
            'image_dir' => '%kernel.root_dir%/../web',
            'prefix' => '/assets/images',
            'driver' => 'gd',
            'mock_image' => '%kernel.debug%',
            'file_levels' => '2:8',
        ];
        $this->assertContainerBuilderHasParameter('ingalabs_image.config', $defaultConfig);
    }

    public function testORMParameterSet()
    {
        $this->load([]);

        $this->assertContainerBuilderHasParameter('ingalabs_image.backend_type_orm', true);
    }

    public function testMongodbParameterSetSet()
    {
        $this->load([
            'doctrine_driver' => 'mongodb',
        ]);

        $this->assertContainerBuilderHasParameter('ingalabs_image.backend_type_mongodb', true);
    }
}
