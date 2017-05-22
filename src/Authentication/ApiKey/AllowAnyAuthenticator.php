<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\ApiKey;

class AllowAnyAuthenticator implements ApiKeyAuthenticatorInterface
{
    /**
     * @param ApiKey $apiKey
     * @throws ApiKeyAuthenticationException
     */
    public function authenticate(ApiKey $apiKey)
    {
    }
}
