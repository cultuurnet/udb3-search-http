<?php

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

class MockOfferRequestParser extends AbstractOfferRequestParser
{
    /**
     * @param Request $request
     * @param OfferQueryBuilderInterface $offerQueryBuilder
     * @return OfferQueryBuilderInterface
     */
    public function parse(Request $request, OfferQueryBuilderInterface $offerQueryBuilder)
    {
        return $offerQueryBuilder;
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @param callable|null $callback
     * @return array
     */
    public function getArrayFromQueryParameter(Request $request, $queryParameter, callable $callback = null)
    {
        return parent::getArrayFromQueryParameter($request, $queryParameter, $callback);
    }

    /**
     * @param Request $request
     * @param string $parameterName
     * @param string|null $defaultValue
     * @param callable $callback
     * @return mixed|null
     */
    public function getStringFromQueryParameter(
        Request $request,
        $parameterName,
        $defaultValue = null,
        callable $callback = null
    ) {
        return parent::getStringFromQueryParameter($request, $parameterName, $defaultValue, $callback);
    }

    /**
     * @param Request $request
     * @param string $parameterName
     * @param string|null $defaultValueAsString
     * @param callable|null $callback
     * @param string $delimiter
     * @return array
     */
    public function getDelimitedStringFromQueryParameter(
        Request $request,
        $parameterName,
        $defaultValueAsString = null,
        callable $callback = null,
        $delimiter = ','
    ) {
        return parent::getDelimitedStringFromQueryParameter(
            $request,
            $parameterName,
            $defaultValueAsString,
            $callback,
            $delimiter
        );
    }

    /**
     * @param Request $request
     * @param string $parameterName
     * @param string|null $defaultValueAsString
     * @return bool|null
     */
    public function getBooleanFromQueryParameter(
        Request $request,
        $parameterName,
        $defaultValueAsString = null
    ) {
        return parent::getBooleanFromQueryParameter($request, $parameterName, $defaultValueAsString);
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @param string|null $defaultValueAsString
     * @return \DateTimeImmutable|null
     */
    public function getDateTimeFromQueryParameter(Request $request, $queryParameter, $defaultValueAsString = null)
    {
        return parent::getDateTimeFromQueryParameter($request, $queryParameter, $defaultValueAsString);
    }
}
