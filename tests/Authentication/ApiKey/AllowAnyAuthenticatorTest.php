<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\ApiKey;

class AllowAnyAuthenticatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_should_never_throw_an_api_key_authentication_exception()
    {
        $randomApiKey = new ApiKey(uniqid());
        $authenticator = new AllowAnyAuthenticator();
        $authenticator->authenticate($randomApiKey);
    }
}
