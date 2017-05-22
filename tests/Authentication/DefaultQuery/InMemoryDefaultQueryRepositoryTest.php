<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\DefaultQuery;

use CultuurNet\UDB3\Search\AbstractQueryString;
use CultuurNet\UDB3\Search\Http\Authentication\ApiKey\ApiKey;

class InMemoryDefaultQueryRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InMemoryDefaultQueryRepository
     */
    private $repository;

    public function setUp()
    {
        $this->repository = new InMemoryDefaultQueryRepository();
    }

    /**
     * @test
     */
    public function it_should_return_null_for_unknown_api_keys()
    {
        $apiKey = new ApiKey(uniqid());
        $this->assertNull($this->repository->get($apiKey));
    }

    /**
     * @test
     */
    public function it_should_return_the_query_of_a_previously_set_api_key_and_prefix_query()
    {
        $apiKey = new ApiKey(uniqid());

        /* @var AbstractQueryString|\PHPUnit_Framework_MockObject_MockObject $prefixQuery */
        $prefixQuery = $this->createMock(AbstractQueryString::class);
        $prefixQuery->expects($this->any())
            ->method('toNative')
            ->willReturn('labels:foo');

        $this->repository->set($apiKey, $prefixQuery);

        $this->assertEquals($prefixQuery, $this->repository->get($apiKey));
    }
}
