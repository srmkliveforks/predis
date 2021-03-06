<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Pipeline;

use Predis\Client;
use Predis\Response;
use PredisTestCase;

/**
 *
 */
class AtomicTest extends PredisTestCase
{
    /**
     * @group disconnected
     */
    public function testPipelineWithSingleConnection()
    {
        $pong = new Response\Status('PONG');
        $queued = new Response\Status('QUEUED');

        $connection = $this->getMockBuilder('Predis\Connection\NodeConnectionInterface')->getMock();
        $connection
            ->expects($this->exactly(2))
            ->method('executeCommand')
            ->will($this->onConsecutiveCalls(true, array($pong, $pong, $pong)));
        $connection
            ->expects($this->exactly(3))
            ->method('writeRequest');
        $connection
            ->expects($this->at(3))
            ->method('readResponse')
            ->will($this->onConsecutiveCalls($queued, $queued, $queued));

        $pipeline = new Atomic(new Client($connection));

        $pipeline->ping();
        $pipeline->ping();
        $pipeline->ping();

        $this->assertSame(array($pong, $pong, $pong), $pipeline->execute());
    }

    /**
     * @group disconnected
     */
    public function testThrowsExceptionOnAbortedTransaction()
    {
        $this->expectException('Predis\ClientException');
        $this->expectExceptionMessage('The underlying transaction has been aborted by the server');

        $connection = $this->getMockBuilder('Predis\Connection\NodeConnectionInterface')->getMock();
        $connection
            ->expects($this->exactly(2))
            ->method('executeCommand')
            ->will($this->onConsecutiveCalls(true, null));

        $pipeline = new Atomic(new Client($connection));

        $pipeline->ping();
        $pipeline->ping();
        $pipeline->ping();

        $pipeline->execute();
    }

    /**
     * @group disconnected
     */
    public function testPipelineWithErrorInTransaction()
    {
        $this->expectException('Predis\Response\ServerException');
        $this->expectExceptionMessage('ERR Test error');

        $queued = new Response\Status('QUEUED');
        $error = new Response\Error('ERR Test error');

        $connection = $this->getMockBuilder('Predis\Connection\NodeConnectionInterface')->getMock();
        $connection
            ->expects($this->at(0))
            ->method('executeCommand')
            ->will($this->returnValue(true));
        $connection
            ->expects($this->exactly(3))
            ->method('readResponse')
            ->will($this->onConsecutiveCalls($queued, $queued, $error));
        $connection
            ->expects($this->at(7))
            ->method('executeCommand')
            ->with($this->isRedisCommand('DISCARD'));

        $pipeline = new Atomic(new Client($connection));

        $pipeline->ping();
        $pipeline->ping();
        $pipeline->ping();

        $pipeline->execute();
    }

    /**
     * @group disconnected
     */
    public function testThrowsServerExceptionOnResponseErrorByDefault()
    {
        $this->expectException('Predis\Response\ServerException');
        $this->expectExceptionMessage('ERR Test error');

        $error = new Response\Error('ERR Test error');

        $connection = $this->getMockBuilder('Predis\Connection\NodeConnectionInterface')->getMock();
        $connection
            ->expects($this->once())
            ->method('readResponse')
            ->will($this->returnValue($error));

        $pipeline = new Atomic(new Client($connection));

        $pipeline->ping();
        $pipeline->ping();

        $pipeline->execute();
    }

    /**
     * @group disconnected
     */
    public function testReturnsResponseErrorWithClientExceptionsSetToFalse()
    {
        $pong = new Response\Status('PONG');
        $queued = new Response\Status('QUEUED');
        $error = new Response\Error('ERR Test error');

        $connection = $this->getMockBuilder('Predis\Connection\NodeConnectionInterface')->getMock();
        $connection
            ->expects($this->exactly(3))
            ->method('readResponse')
            ->will($this->onConsecutiveCalls($queued, $queued, $queued));
        $connection
            ->expects($this->at(7))
            ->method('executeCommand')
            ->will($this->returnValue(array($pong, $pong, $error)));

        $pipeline = new Atomic(new Client($connection, array('exceptions' => false)));

        $pipeline->ping();
        $pipeline->ping();
        $pipeline->ping();

        $this->assertSame(array($pong, $pong, $error), $pipeline->execute());
    }

    /**
     * @group disconnected
     */
    public function testExecutorWithAggregateConnection()
    {
        $this->expectException('Predis\ClientException');
        $this->expectExceptionMessage("The class 'Predis\Pipeline\Atomic' does not support aggregate connections");

        $connection = $this->getMockBuilder('Predis\Connection\AggregateConnectionInterface')->getMock();
        $pipeline = new Atomic(new Client($connection));

        $pipeline->ping();

        $pipeline->execute();
    }
}
