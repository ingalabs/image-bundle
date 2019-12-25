<?php

/*
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IngaLabs\Bundle\ImageBundle\Tests;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
trait ExceptionWrapperTestCaseTrait
{
    protected function expectExceptionWrapper($exception, $regexp = null)
    {
        $this->expectException($exception);
        if (null !== $regexp) {
            $this->expectExceptionMessageRegExp($regexp);
        }
    }
}
