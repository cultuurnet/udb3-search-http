<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\DefaultQuery;

use CultuurNet\UDB3\Search\AbstractQueryString;
use CultuurNet\UDB3\Search\Http\Authentication\ApiKey\ApiKey;

class InMemoryDefaultQueryRepository implements
    DefaultQueryReadRepositoryInterface,
    DefaultQueryWriteRepositoryInterface
{
    /**
     * @var AbstractQueryString[]
     */
    private $queries;

    /**
     * @param ApiKey $apiKey
     * @param AbstractQueryString $prefixQuery
     */
    public function set(ApiKey $apiKey, AbstractQueryString $prefixQuery)
    {
        $this->queries[$apiKey->toNative()] = $prefixQuery;
    }

    /**
     * @param ApiKey $apiKey
     * @return AbstractQueryString|null
     */
    public function get(ApiKey $apiKey)
    {
        if (isset($this->queries[$apiKey->toNative()])) {
            return $this->queries[$apiKey->toNative()];
        } else {
            return null;
        }
    }
}
