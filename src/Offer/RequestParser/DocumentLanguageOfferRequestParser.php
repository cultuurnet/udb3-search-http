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

        $mainLanguage = $parameterBagReader->getStringFromParameter(
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
     * @param SymfonyParameterBagAdapter $parameterBagReader
     * @param string $queryParameter
     * @return Language[]
     */
    private function getLanguagesFromQuery(SymfonyParameterBagAdapter $parameterBagReader, $queryParameter)
    {
        return $parameterBagReader->getArrayFromParameter(
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
