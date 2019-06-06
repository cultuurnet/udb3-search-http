<?php

namespace CultuurNet\UDB3\Search\Http\Organizer\RequestParser;

use CultuurNet\UDB3\Search\Organizer\OrganizerQueryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

interface OrganizerRequestParser
{
    public function parse(
        Request $request,
        OrganizerQueryBuilderInterface $organizerQueryBuilder
    ): OrganizerQueryBuilderInterface;
}
