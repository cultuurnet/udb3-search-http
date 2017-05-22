<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\DefaultQuery;

use CultuurNet\UDB3\Search\AbstractQueryString;
use CultuurNet\UDB3\Search\Http\Authentication\ApiKey\ApiKey;

interface DefaultQueryReadRepositoryInterface
{
    /**
     * @param ApiKey $apiKey
     * @return AbstractQueryString|null
     */
    public function get(ApiKey $apiKey);
}
