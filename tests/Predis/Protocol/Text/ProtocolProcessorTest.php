<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Protocol\Text;

use PredisTestCase;

/**
 *
 */
class ProtocolProcessorTest extends PredisTestCase
{
    /**
     * @group disconnected
     */
    public function testConnectionWrite()
    {
        $serialized = "*1\r\n$4\r\nPING\r\n";
        $protocol = new ProtocolProcessor();

        $command = $this->getMockBuilder('Predis\Command\CommandInterface')->getMock();
        $command
            ->expects($this->once())
            ->method('getId')
            ->will($this->returnValue('PING'));
        $command
            ->expects($this->once())
            ->method('getArguments')
            ->will($this->returnValue(array()));

        $connection = $this->getMockConnectionOfType('Predis\Connection\CompositeConnectionInterface');
        $connection
            ->expects($this->once())
            ->method('writeBuffer')
            ->with($this->equalTo($serialized));

        $protocol->write($connection, $command);
    }

    /**
     * @group disconnected
     */
    public function testConnectionRead()
    {
        $protocol = new ProtocolProcessor();

        $connection = $this->getMockConnectionOfType('Predis\Connection\CompositeConnectionInterface');
        $connection
            ->expects($this->at(0))
            ->method('readLine')
            ->will($this->returnValue('+OK'));
        $connection
            ->expects($this->at(1))
            ->method('readLine')
            ->will($this->returnValue('-ERR error message'));
        $connection
            ->expects($this->at(2))
            ->method('readLine')
            ->will($this->returnValue(':2'));
        $connection
            ->expects($this->at(3))
            ->method('readLine')
            ->will($this->returnValue('$-1'));
        $connection
            ->expects($this->at(4))
            ->method('readLine')
            ->will($this->returnValue('*-1'));

        $this->assertEquals('OK', $protocol->read($connection));
        $this->assertEquals('ERR error message', $protocol->read($connection));
        $this->assertSame(2, $protocol->read($connection));
        $this->assertNull($protocol->read($connection));
        $this->assertNull($protocol->read($connection));
    }

    /**
     * @group disconnected
     */
    public function testIterableMultibulkSupport()
    {
        $protocol = new ProtocolProcessor();
        $protocol->useIterableMultibulk(true);

        $connection = $this->getMockConnectionOfType('Predis\Connection\CompositeConnectionInterface');
        $connection
            ->expects($this->once(4))
            ->method('readLine')
            ->will($this->returnValue('*1'));

        $this->assertInstanceOf('Predis\Response\Iterator\MultiBulk', $protocol->read($connection));
    }

    /**
     * @group disconnected
     */
    public function testUnknownResponsePrefix()
    {
        $this->expectException('Predis\Protocol\ProtocolException');
        $this->expectExceptionMessage("Unknown response prefix: '!' [tcp://127.0.0.1:6379]");

        $protocol = new ProtocolProcessor();

        $connection = $this->getMockConnectionOfType('Predis\Connection\CompositeConnectionInterface', 'tcp://127.0.0.1:6379');
        $connection
            ->expects($this->once())
            ->method('readLine')
            ->will($this->returnValue('!'));

        $protocol->read($connection);
    }
}
