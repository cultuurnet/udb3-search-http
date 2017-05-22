<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\ApiKeyReader;

use CultuurNet\UDB3\Search\Authentication\ApiKey;
use Symfony\Component\HttpFoundation\Request;

class CompositeApiKeyReader implements ApiKeyReaderInterface
{
    /**
     * @var ApiKeyReaderInterface[]
     */
    private $apiKeyReaders;

    /**
     * @param ApiKeyReaderInterface[] ...$apiKeyReaders
     */
    public function __construct(ApiKeyReaderInterface ...$apiKeyReaders)
    {
        $this->apiKeyReaders = $apiKeyReaders;
    }

    /**
     * @param Request $request
     * @return ApiKey|null
     */
    public function read(Request $request)
    {
        foreach ($this->apiKeyReaders as $apiKeyReader) {
            $apiKey = $apiKeyReader->read($request);

            if ($apiKey) {
                return $apiKey;
            }
        }

        return null;
    }
}
