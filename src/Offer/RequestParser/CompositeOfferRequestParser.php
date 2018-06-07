<?php

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

class CompositeOfferRequestParser implements OfferRequestParserInterface
{
    /**
     * @var OfferRequestParserInterface[]
     */
    private $parsers = [];

    public function __construct()
    {
        $this->parsers = [
            new DocumentLanguageOfferRequestParser(),
            new AgeRangeOfferRequestParser(),
        ];
    }

    /**
     * @param Request $request
     * @param OfferQueryBuilderInterface $offerQueryBuilder
     * @return OfferQueryBuilderInterface
     */
    public function parse(Request $request, OfferQueryBuilderInterface $offerQueryBuilder)
    {
        foreach ($this->parsers as $parser) {
            $offerQueryBuilder = $parser->parse($request, $offerQueryBuilder);
        }
        return $offerQueryBuilder;
    }
}
