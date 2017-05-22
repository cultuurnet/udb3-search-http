<?php

namespace CultuurNet\UDB3\Search\Http\Authentication\Request;

use Symfony\Component\HttpFoundation\Request;

interface RequestAuthenticatorInterface
{
    /**
     * @param Request $request
     * @throws RequestAuthenticationException
     */
    public function authenticate(Request $request);
}
