<?php

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Search\Http\ParameterBagParser;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

class DocumentLanguageOfferRequestParser implements OfferRequestParserInterface
{
    /**
     * @var ParameterBagParser
     */
    private $parameterBagParser;

    /**
     * @param ParameterBagParser $parameterBagParser
     */
    public function __construct(ParameterBagParser $parameterBagParser)
    {
        $this->parameterBagParser = $parameterBagParser;
    }

    /**
     * @param Request $request
     * @param OfferQueryBuilderInterface $offerQueryBuilder
     * @return OfferQueryBuilderInterface
     */
    public function parse(Request $request, OfferQueryBuilderInterface $offerQueryBuilder)
    {
        $mainLanguage = $this->parameterBagParser->getStringFromQueryParameter(
            $request,
            'mainLanguage',
            null,
            $this->getLanguageCallback()
        );

        if ($mainLanguage) {
            $offerQueryBuilder = $offerQueryBuilder->withMainLanguageFilter($mainLanguage);
        }

        $languages = $this->getLanguagesFromQuery($request, 'languages');
        foreach ($languages as $language) {
            $offerQueryBuilder = $offerQueryBuilder->withLanguageFilter($language);
        }

        $completedLanguages = $this->getLanguagesFromQuery($request, 'completedLanguages');
        foreach ($completedLanguages as $completedLanguage) {
            $offerQueryBuilder = $offerQueryBuilder->withCompletedLanguageFilter($completedLanguage);
        }

        return $offerQueryBuilder;
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return Language[]
     */
    private function getLanguagesFromQuery(Request $request, $queryParameter)
    {
        return $this->parameterBagParser->getArrayFromQueryParameter(
            $request,
            $queryParameter,
            $this->getLanguageCallback()
        );
    }

    /**
     * @return \Closure
     */
    private function getLanguageCallback()
    {
        return function ($value) {
            return new Language($value);
        };
    }
}
