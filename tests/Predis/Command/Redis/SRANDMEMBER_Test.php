<?php

/*
 * This file is part of the Predis package.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Predis\Command\Redis;

/**
 * @group commands
 * @group realm-set
 */
class SRANDMEMBER_Test extends PredisCommandTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getExpectedCommand()
    {
        return 'Predis\Command\Redis\SRANDMEMBER';
    }

    /**
     * {@inheritdoc}
     */
    protected function getExpectedId()
    {
        return 'SRANDMEMBER';
    }

    /**
     * @group disconnected
     */
    public function testFilterArguments()
    {
        $arguments = array('key', 1);
        $expected = array('key', 1);

        $command = $this->getCommand();
        $command->setArguments($arguments);

        $this->assertSame($expected, $command->getArguments());
    }

    /**
     * @group disconnected
     */
    public function testParseResponse()
    {
        $this->assertSame('member', $this->getCommand()->parseResponse('member'));
    }

    /**
     * @group connected
     */
    public function testReturnsRandomMemberFromSet()
    {
        $redis = $this->getClient();

        $redis->sadd('letters', 'a', 'b');

        $this->assertContains($redis->srandmember('letters'), array('a', 'b'));
        $this->assertContains($redis->srandmember('letters'), array('a', 'b'));

        $this->assertSame(2, $redis->scard('letters'));
    }

    /**
     * @group connected
     */
    public function testReturnsNullOnNonExistingSet()
    {
        $this->assertNull($this->getClient()->srandmember('letters'));
    }

    /**
     * @group connected
     */
    public function testThrowsExceptionOnWrongType()
    {
        $this->expectException('Predis\Response\ServerException');
        $this->expectExceptionMessage('Operation against a key holding the wrong kind of value');

        $redis = $this->getClient();

        $redis->set('foo', 'bar');
        $redis->srandmember('foo');
    }
}
