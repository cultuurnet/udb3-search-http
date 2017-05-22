<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\ApiKeyReader;

use CultuurNet\UDB3\Search\Authentication\ApiKey;
use Symfony\Component\HttpFoundation\Request;

interface ApiKeyReaderInterface
{
    /**
     * @param Request $request
     * @return ApiKey|null
     */
    public function read(Request $request);
}
