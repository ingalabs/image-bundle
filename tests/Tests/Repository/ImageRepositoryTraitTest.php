<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests\Repository;

use Doctrine\Common\Persistence\ObjectRepository;
use IngaLabs\Bundle\ImageBundle\Repository\ImageRepositoryTrait;

/**
 * ImageRepositoryTraitTest.
 *
 * @author Antal Áron <antalaron@antalaron.hu>
 */
class ImageRepositoryTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testFindOneByHashCallsTheAbstractMethod()
    {
        $repository = $this->getMockForAbstractClass(ObjectRepositoryWithImageRepositoryTraitTestClass::class);

        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->will($this->returnCallback(function ($criteria) {
                return $criteria;
            }));

        $this->assertSame(['hash' => 'foo_bar'], $repository->findOneByHash('foo_bar'));
    }
}

abstract class ObjectRepositoryWithImageRepositoryTraitTestClass implements ObjectRepository
{
    use ImageRepositoryTrait;
}
