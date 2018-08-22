<?php

namespace OpenStack\Test\ObjectStore\v1;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OpenStack\Common\Error\BadResponseError;
use OpenStack\ObjectStore\v1\Api;
use OpenStack\ObjectStore\v1\Models\Account;
use OpenStack\ObjectStore\v1\Models\Container;
use OpenStack\ObjectStore\v1\Service;
use OpenStack\Test\TestCase;

class ServiceTest extends TestCase
{
    /** @var Service */
    private $service;

    public function setUp()
    {
        parent::setUp();

        $this->rootFixturesDir = __DIR__;

        $this->service = new Service($this->client->reveal(), new Api());
    }

    public function test_Account()
    {
        $this->assertInstanceOf(Account::class, $this->service->getAccount());
    }

    public function test_it_lists_containers()
    {
        $this->client
            ->request('GET', '', ['query' => ['limit' => 2, 'format' => 'json'], 'headers' => []])
            ->shouldBeCalled()
            ->willReturn($this->getFixture('GET_Container'));

        foreach ($this->service->listContainers(['limit' => 2]) as $container) {
            $this->assertInstanceOf(Container::class, $container);
        }
    }

    public function test_It_Create_Containers()
    {
        $this->setupMock('PUT', 'foo', null, [], 'Created');
        $this->service->createContainer(['name' => 'foo']);
    }

    public function test_it_returns_true_for_existing_containers()
    {
        $this->setupMock('HEAD', 'foo', null, [], new Response(200));

        $this->assertTrue($this->service->containerExists('foo'));
    }

    public function test_it_returns_false_if_container_does_not_exist()
    {
        $e = new BadResponseError();
        $e->setRequest(new Request('HEAD', 'foo'));
        $e->setResponse(new Response(404));

        $this->client
            ->request('HEAD', 'foo', ['headers' => []])
            ->shouldBeCalled()
            ->willThrow($e);

        $this->assertFalse($this->service->containerExists('foo'));
    }

    /**
     * @expectedException \OpenStack\Common\Error\BadResponseError
     */
    public function test_it_throws_exception_when_error()
    {
        $e = new BadResponseError();
        $e->setRequest(new Request('HEAD', 'foo'));
        $e->setResponse(new Response(500));

        $this->client
            ->request('HEAD', 'foo', ['headers' => []])
            ->shouldBeCalled()
            ->willThrow($e);

        $this->assertFalse($this->service->containerExists('foo'));
    }

    public function test_it_generates_temp_url_sha1()
    {
        $cases = [
            [
                ['GET', '1516741234', '/v1/AUTH_account/container/object', 'mykey'],
                '/v1/AUTH_account/container/object?temp_url_sig=712dcef48d391e39bd2e3b63fc0a07146a36055e&temp_url_expires=1516741234'
            ],
            [
                ['HEAD', '1516741234', '/v1/AUTH_account/container/object', 'somekey'],
                '/v1/AUTH_account/container/object?temp_url_sig=a4516e93f2023652641fec44c82163dc298620e8&temp_url_expires=1516741234'
            ],
            [
                ['GET', '1323479485', 'prefix:/v1/AUTH_account/container/pre', 'mykey'],
                '/v1/AUTH_account/container/object?temp_url_sig=a4516e93f2023652641fec44c82163dc298620e8&temp_url_expires=1516741234'
            ]
        ];

        foreach ($cases as $case)
        {
            $params = $case[0];
            $expected = $case[1];

            $actual = call_user_func_array([$this->service, 'tempUrl'], $params);
            $this->assertEquals($expected, $actual);
        }
    }
}
