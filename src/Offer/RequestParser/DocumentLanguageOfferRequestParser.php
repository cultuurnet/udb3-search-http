<?php

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Search\Http\Parameters\SymfonyParameterBagAdapter;
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
        $parameterBagReader = new SymfonyParameterBagAdapter($request->query);

        $languageCallback = function ($value) {
            return new Language($value);
        };

        // Add mainLanguage parameter as a filter.
        $mainLanguage = $parameterBagReader->getStringFromParameter('mainLanguage', null, $languageCallback);
        if ($mainLanguage) {
            $offerQueryBuilder = $offerQueryBuilder->withMainLanguageFilter($mainLanguage);
        }

        // Add languages parameter(s) as filter(s).
        $languages = $parameterBagReader->getArrayFromParameter('languages', $languageCallback);
        foreach ($languages as $language) {
            $offerQueryBuilder = $offerQueryBuilder->withLanguageFilter($language);
        }

        // Add completedLanguages parameter(s) as filter(s).
        $completedLanguages = $parameterBagReader->getArrayFromParameter('completedLanguages', $languageCallback);
        foreach ($completedLanguages as $completedLanguage) {
            $offerQueryBuilder = $offerQueryBuilder->withCompletedLanguageFilter($completedLanguage);
        }

        return $offerQueryBuilder;
    }
}
