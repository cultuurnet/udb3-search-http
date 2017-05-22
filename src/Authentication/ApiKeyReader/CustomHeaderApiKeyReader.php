<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\ApiKeyReader;

use CultuurNet\UDB3\Search\Authentication\ApiKey;
use Symfony\Component\HttpFoundation\Request;

class CustomHeaderApiKeyReader implements ApiKeyReaderInterface
{
    /**
     * @var string
     */
    private $headerName;

    /**
     * @param string $headerName
     */
    public function __construct($headerName)
    {
        $this->headerName = (string) $headerName;
    }

    /**
     * @param Request $request
     * @return ApiKey|null
     */
    public function read(Request $request)
    {
        $apiKeyAsString = (string) $request->headers->get($this->headerName, '');

        if (strlen($apiKeyAsString) == 0) {
            return null;
        } else {
            return new ApiKey($apiKeyAsString);
        }
    }
}
