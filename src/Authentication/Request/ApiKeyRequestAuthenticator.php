<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\Request;

use CultuurNet\UDB3\Search\Http\Authentication\ApiKey\ApiKeyAuthenticationException;
use CultuurNet\UDB3\Search\Http\Authentication\ApiKey\ApiKeyAuthenticatorInterface;
use CultuurNet\UDB3\Search\Http\Authentication\ApiKey\Reader\ApiKeyReaderInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiKeyRequestAuthenticator implements RequestAuthenticatorInterface
{
    /**
     * @var ApiKeyReaderInterface
     */
    private $apiKeyReader;

    /**
     * @var ApiKeyAuthenticatorInterface
     */
    private $apiKeyAuthenticator;

    /**
     * @param ApiKeyReaderInterface $apiKeyReader
     * @param ApiKeyAuthenticatorInterface $apiKeyAuthenticator
     */
    public function __construct(
        ApiKeyReaderInterface $apiKeyReader,
        ApiKeyAuthenticatorInterface $apiKeyAuthenticator
    ) {
        $this->apiKeyReader = $apiKeyReader;
        $this->apiKeyAuthenticator = $apiKeyAuthenticator;
    }

    /**
     * @param Request $request
     * @throws RequestAuthenticationException
     */
    public function authenticate(Request $request)
    {
        $apiKey = $this->apiKeyReader->read($request);

        if (is_null($apiKey)) {
            throw new RequestAuthenticationException('No API key provided.');
        }

        try {
            $this->apiKeyAuthenticator->authenticate($apiKey);
        } catch (ApiKeyAuthenticationException $e) {
            throw new RequestAuthenticationException("Invalid API key provided ({$apiKey->toNative()}).");
        }
    }
}
