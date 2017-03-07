<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\DependencyInjection;

use IngaLabs\Bundle\ImageBundle\DependencyInjection\Configuration;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;

/**
 * ConfigurationTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration()
    {
        return new Configuration();
    }

    public function testDefaultValues()
    {
        $this->assertProcessedConfigurationEquals(
            [],
            [
                'doctrine_driver' => 'orm',
                'image_dir' => '%kernel.root_dir%/../web',
                'prefix' => '/assets/images',
                'driver' => 'gd',
                'mock_image' => '%kernel.debug%',
            ]
        );
    }

    public function testMongodbDoctrineDriver()
    {
        $this->assertProcessedConfigurationEquals(
            [
                ['doctrine_driver' => 'mongodb'],
            ],
            [
                'doctrine_driver' => 'mongodb',
                'image_dir' => '%kernel.root_dir%/../web',
                'prefix' => '/assets/images',
                'driver' => 'gd',
                'mock_image' => '%kernel.debug%',
            ]
        );
    }

    public function testInvalidDoctrineDriver()
    {
        $this->assertPartialConfigurationIsInvalid(
            [
                ['doctrine_driver' => 'foo_bar'],
            ],
            'doctrine_driver'
        );
    }
}
