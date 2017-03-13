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
                'file_levels' => '2:8',
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
                'file_levels' => '2:8',
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

    public function testInvalidFileLevels()
    {
        $this->assertPartialConfigurationIsInvalid(
            [
                ['file_levels' => '2:e:3'],
            ],
            'file_levels'
        );
        $this->assertPartialConfigurationIsInvalid(
            [
                ['file_levels' => ''],
            ],
            'file_levels'
        );
        $this->assertPartialConfigurationIsInvalid(
            [
                ['file_levels' => '::'],
            ],
            'file_levels'
        );
        $this->assertPartialConfigurationIsInvalid(
            [
                ['file_levels' => '1:33'],
            ],
            'file_levels'
        );
        $this->assertPartialConfigurationIsInvalid(
            [
                ['file_levels' => '1:-3'],
            ],
            'file_levels'
        );
    }

    public function testValidFileLevels()
    {
        $this->assertConfigurationIsValid(
            [
                ['file_levels' => '2:2:3'],
            ],
            'file_levels'
        );
        $this->assertConfigurationIsValid(
            [
                ['file_levels' => '2'],
            ],
            'file_levels'
        );
    }
}
