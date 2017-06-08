<?php

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Search\Http\ParameterBagReader;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

class DocumentLanguageOfferRequestParser implements OfferRequestParserInterface
{
    /**
     * @param Request $request
     * @param OfferQueryBuilderInterface $offerQueryBuilder
     * @return OfferQueryBuilderInterface
     */
    public function parse(Request $request, OfferQueryBuilderInterface $offerQueryBuilder)
    {
        $parameterBagReader = new ParameterBagReader($request->query);

        $mainLanguage = $parameterBagReader->getStringFromQueryParameter(
            'mainLanguage',
            null,
            $this->getLanguageCallback()
        );

        if ($mainLanguage) {
            $offerQueryBuilder = $offerQueryBuilder->withMainLanguageFilter($mainLanguage);
        }

        $languages = $this->getLanguagesFromQuery($parameterBagReader, 'languages');
        foreach ($languages as $language) {
            $offerQueryBuilder = $offerQueryBuilder->withLanguageFilter($language);
        }

        $completedLanguages = $this->getLanguagesFromQuery($parameterBagReader, 'completedLanguages');
        foreach ($completedLanguages as $completedLanguage) {
            $offerQueryBuilder = $offerQueryBuilder->withCompletedLanguageFilter($completedLanguage);
        }

        return $offerQueryBuilder;
    }

    /**
     * @param ParameterBagReader $parameterBagReader
     * @param string $queryParameter
     * @return Language[]
     */
    private function getLanguagesFromQuery(ParameterBagReader $parameterBagReader, $queryParameter)
    {
        return $parameterBagReader->getArrayFromQueryParameter(
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
