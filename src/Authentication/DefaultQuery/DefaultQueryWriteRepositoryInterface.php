<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\DefaultQuery;

use CultuurNet\UDB3\Search\AbstractQueryString;
use CultuurNet\UDB3\Search\Http\Authentication\ApiKey\ApiKey;

interface DefaultQueryWriteRepositoryInterface
{
    /**
     * @param ApiKey $apiKey
     * @param AbstractQueryString $prefixQuery
     */
    public function set(ApiKey $apiKey, AbstractQueryString $prefixQuery);
}
