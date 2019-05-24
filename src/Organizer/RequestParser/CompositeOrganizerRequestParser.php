<?php

namespace CultuurNet\UDB3\Search\Http\Organizer\RequestParser;

use CultuurNet\UDB3\Search\Organizer\OrganizerQueryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

class CompositeOrganizerRequestParser implements OrganizerRequestParser
{
    /**
     * @var OrganizerRequestParser[]
     */
    private $parsers = [];

    public function __construct()
    {
        $this->parsers = [];
    }

    public function withParser(OrganizerRequestParser $parser)
    {
        $c = clone $this;
        $c->parsers[] = $parser;
        return $c;
    }

    public function parse(
        Request $request,
        OrganizerQueryBuilderInterface $organizerQueryBuilder
    ): OrganizerQueryBuilderInterface {
        foreach ($this->parsers as $parser) {
            $organizerQueryBuilder = $parser->parse($request, $organizerQueryBuilder);
        }
        return $organizerQueryBuilder;
    }
}
