<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\ApiKeyReader;

use CultuurNet\UDB3\Search\Authentication\ApiKey;
use Symfony\Component\HttpFoundation\Request;

class QueryParameterApiKeyReader implements ApiKeyReaderInterface
{
    /**
     * @var string
     */
    private $queryParameterName;

    /**
     * @param string $queryParameterName
     */
    public function __construct($queryParameterName)
    {
        $this->queryParameterName = (string) $queryParameterName;
    }

    /**
     * @param Request $request
     * @return ApiKey|null
     */
    public function read(Request $request)
    {
        $apiKeyAsString = (string) $request->query->get($this->queryParameterName, '');

        if (strlen($apiKeyAsString) == 0) {
            return null;
        } else {
            return new ApiKey($apiKeyAsString);
        }
    }
}
