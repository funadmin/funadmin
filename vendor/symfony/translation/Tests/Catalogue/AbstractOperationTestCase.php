<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Catalogue;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

abstract class AbstractOperationTestCase extends TestCase
{
    public function testGetEmptyDomains()
    {
        $this->assertEquals(
            [],
            $this->createOperation(
                new MessageCatalogue('en'),
                new MessageCatalogue('en')
            )->getDomains()
        );
    }

    public function testGetMergedDomains()
    {
        $this->assertEquals(
            ['a', 'b', 'c'],
            $this->createOperation(
                new MessageCatalogue('en', ['a' => [], 'b' => []]),
                new MessageCatalogue('en', ['b' => [], 'c' => []])
            )->getDomains()
        );
    }

    public function testGetMessagesFromUnknownDomain()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createOperation(
            new MessageCatalogue('en'),
            new MessageCatalogue('en')
        )->getMessages('domain');
    }

    public function testGetEmptyMessages()
    {
        $this->assertEquals(
            [],
            $this->createOperation(
                new MessageCatalogue('en', ['a' => []]),
                new MessageCatalogue('en')
            )->getMessages('a')
        );
    }

    public function testGetEmptyResult()
    {
        $this->assertEquals(
            new MessageCatalogue('en'),
            $this->createOperation(
                new MessageCatalogue('en'),
                new MessageCatalogue('en')
            )->getResult()
        );
    }

    public function testSourceAndTargetWithDifferentLocales()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Operated catalogues must belong to the same locale.');
        $this->createOperation(
            new MessageCatalogue('en'),
            new MessageCatalogue('fr')
        );
    }

    abstract protected function createOperation(MessageCatalogueInterface $source, MessageCatalogueInterface $target);
}
