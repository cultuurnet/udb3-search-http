<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\QueryStringFactoryInterface;

class MockQueryStringFactory implements QueryStringFactoryInterface
{
    /**
     * @param string $queryString
     * @return MockQueryString
     */
    public function fromString($queryString)
    {
        return new MockQueryString($queryString);
    }
}
