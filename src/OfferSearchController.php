<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Geocoding\Coordinate\Coordinates;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\Search\Creator;
use CultuurNet\UDB3\Search\DistanceFactoryInterface;
use CultuurNet\UDB3\Search\GeoDistanceParameters;
use CultuurNet\UDB3\Search\Http\Offer\RequestParser\OfferRequestParserInterface;
use CultuurNet\UDB3\Search\Http\Parameters\OfferParameterWhiteList;
use CultuurNet\UDB3\Search\Offer\AudienceType;
use CultuurNet\UDB3\Search\Offer\CalendarType;
use CultuurNet\UDB3\Search\Offer\Cdbid;
use CultuurNet\UDB3\Search\Offer\FacetName;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use CultuurNet\UDB3\Search\Offer\OfferSearchServiceInterface;
use CultuurNet\UDB3\Search\Offer\WorkflowStatus;
use CultuurNet\UDB3\Search\Offer\TermId;
use CultuurNet\UDB3\Search\Offer\TermLabel;
use CultuurNet\UDB3\Search\QueryStringFactoryInterface;
use CultuurNet\UDB3\Search\Region\RegionId;
use CultuurNet\UDB3\Search\SortOrder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueObjects\Geography\Country;
use ValueObjects\Geography\CountryCode;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;

class OfferSearchController
{
    /**
     * Used to reset filters with default values.
     * Eg., countryCode is default BE but can be reset by specifying
     * ?countryCode=*
     */
    const QUERY_PARAMETER_RESET_VALUE = '*';

    /**
     * @var OfferQueryBuilderInterface
     */
    private $queryBuilder;

    /**
     * @var OfferSearchServiceInterface
     */
    private $searchService;

    /**
     * @var StringLiteral
     */
    private $regionIndexName;

    /**
     * @var StringLiteral
     */
    private $regionDocumentType;

    /**
     * @var QueryStringFactoryInterface
     */
    private $queryStringFactory;

    /**
     * @var DistanceFactoryInterface
     */
    private $distanceFactory;

    /**
     * @var FacetTreeNormalizerInterface
     */
    private $facetTreeNormalizer;

    /**
     * @var PagedCollectionFactoryInterface
     */
    private $pagedCollectionFactory;

    /**
     * @var OfferParameterWhiteList
     */
    private $offerParameterWhiteList;

    /**
     * @param OfferQueryBuilderInterface $queryBuilder
     * @param OfferRequestParserInterface $offerRequestParser
     * @param OfferSearchServiceInterface $searchService
     * @param StringLiteral $regionIndexName
     * @param StringLiteral $regionDocumentType
     * @param QueryStringFactoryInterface $queryStringFactory
     * @param DistanceFactoryInterface $distanceFactory
     * @param FacetTreeNormalizerInterface $facetTreeNormalizer
     * @param PagedCollectionFactoryInterface|null $pagedCollectionFactory
     */
    public function __construct(
        OfferQueryBuilderInterface $queryBuilder,
        OfferRequestParserInterface $offerRequestParser,
        OfferSearchServiceInterface $searchService,
        StringLiteral $regionIndexName,
        StringLiteral $regionDocumentType,
        QueryStringFactoryInterface $queryStringFactory,
        DistanceFactoryInterface $distanceFactory,
        FacetTreeNormalizerInterface $facetTreeNormalizer,
        PagedCollectionFactoryInterface $pagedCollectionFactory = null
    ) {
        if (is_null($pagedCollectionFactory)) {
            $pagedCollectionFactory = new ResultSetMappingPagedCollectionFactory();
        }

        $this->queryBuilder = $queryBuilder;
        $this->requestParser = $offerRequestParser;
        $this->searchService = $searchService;
        $this->regionIndexName = $regionIndexName;
        $this->regionDocumentType = $regionDocumentType;
        $this->queryStringFactory = $queryStringFactory;
        $this->distanceFactory = $distanceFactory;
        $this->facetTreeNormalizer = $facetTreeNormalizer;
        $this->pagedCollectionFactory = $pagedCollectionFactory;
        $this->offerParameterWhiteList = new OfferParameterWhiteList();
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function search(Request $request)
    {
        $this->offerParameterWhiteList->validateParameters(
            $request->query->keys()
        );

        $start = (int) $request->query->get('start', 0);
        $limit = (int) $request->query->get('limit', 30);

        if ($limit == 0) {
            $limit = 30;
        }

        $queryBuilder = $this->queryBuilder
            ->withStart(new Natural($start))
            ->withLimit(new Natural($limit));

        $queryBuilder = $this->requestParser->parse($request, $queryBuilder);

        $textLanguages = $this->getLanguagesFromQuery($request, 'textLanguages');

        if (!empty($request->query->get('q'))) {
            $queryBuilder = $queryBuilder->withAdvancedQuery(
                $this->queryStringFactory->fromString(
                    $request->query->get('q')
                ),
                ...$textLanguages
            );
        }

        if (!empty($request->query->get('text'))) {
            $queryBuilder = $queryBuilder->withTextQuery(
                new StringLiteral($request->query->get('text')),
                ...$textLanguages
            );
        }

        if (!empty($request->query->get('id'))) {
            $queryBuilder = $queryBuilder->withCdbIdFilter(
                new Cdbid($request->query->get('id'))
            );
        }

        if (!empty($request->query->get('locationId'))) {
            $queryBuilder = $queryBuilder->withLocationCdbIdFilter(
                new Cdbid($request->query->get('locationId'))
            );
        }

        if (!empty($request->query->get('organizerId'))) {
            $queryBuilder = $queryBuilder->withOrganizerCdbIdFilter(
                new Cdbid($request->query->get('organizerId'))
            );
        }

        $workflowStatuses = $this->getWorkflowStatusesFromQuery($request);
        if (!empty($workflowStatuses)) {
            $queryBuilder = $queryBuilder->withWorkflowStatusFilter(...$workflowStatuses);
        }

        $availableFrom = $this->getAvailabilityFromQuery($request, 'availableFrom');
        $availableTo = $this->getAvailabilityFromQuery($request, 'availableTo');
        if ($availableFrom || $availableTo) {
            $queryBuilder = $queryBuilder->withAvailableRangeFilter($availableFrom, $availableTo);
        }

        $regionIds = $this->getRegionIdsFromQuery($request, 'regions');
        foreach ($regionIds as $regionId) {
            $queryBuilder = $queryBuilder->withRegionFilter(
                $this->regionIndexName,
                $this->regionDocumentType,
                $regionId
            );
        }

        $coordinates = $request->query->get('coordinates', false);
        $distance = $request->query->get('distance', false);

        if ($coordinates && !$distance) {
            throw new \InvalidArgumentException('Required "distance" parameter missing when searching by coordinates.');
        } elseif ($distance && !$coordinates) {
            throw new \InvalidArgumentException('Required "coordinates" parameter missing when searching by distance.');
        } elseif ($coordinates && $distance) {
            $coordinates = Coordinates::fromLatLonString($coordinates);

            $queryBuilder = $queryBuilder->withGeoDistanceFilter(
                new GeoDistanceParameters(
                    $coordinates,
                    $this->distanceFactory->fromString($distance)
                )
            );
        }

        $postalCode = (string) $request->query->get('postalCode');
        if (!empty($postalCode)) {
            $queryBuilder = $queryBuilder->withPostalCodeFilter(
                new PostalCode($postalCode)
            );
        }

        $country = $this->getAddressCountryFromQuery($request);
        if (!empty($country)) {
            $queryBuilder = $queryBuilder->withAddressCountryFilter($country);
        }

        $audienceType = $this->getAudienceTypeFromQuery($request);
        if (!empty($audienceType)) {
            $queryBuilder = $queryBuilder->withAudienceTypeFilter($audienceType);
        }

        $minAge = $request->query->get('minAge', null);
        $maxAge = $request->query->get('maxAge', null);
        if (!is_null($minAge) || !is_null($maxAge)) {
            $minAge = is_null($minAge) ? null : new Natural((int) $minAge);
            $maxAge = is_null($maxAge) ? null : new Natural((int) $maxAge);

            $queryBuilder = $queryBuilder->withAgeRangeFilter($minAge, $maxAge);
        }

        $price = $request->query->get('price', null);
        $minPrice = $request->query->get('minPrice', null);
        $maxPrice = $request->query->get('maxPrice', null);

        if (!is_null($price)) {
            $price = Price::fromFloat((float) $price);
            $queryBuilder = $queryBuilder->withPriceRangeFilter($price, $price);
        } elseif (!is_null($minPrice) || !is_null($maxPrice)) {
            $minPrice = is_null($minPrice) ? null : Price::fromFloat((float) $minPrice);
            $maxPrice = is_null($maxPrice) ? null : Price::fromFloat((float) $maxPrice);

            $queryBuilder = $queryBuilder->withPriceRangeFilter($minPrice, $maxPrice);
        }

        $includeMediaObjects = $this->castMixedToBool($request->query->get('hasMediaObjects', null));
        if (!is_null($includeMediaObjects)) {
            $queryBuilder = $queryBuilder->withMediaObjectsFilter($includeMediaObjects);
        }

        $includeUiTPAS = $this->castMixedToBool($request->query->get('uitpas', null));
        if (!is_null($includeUiTPAS)) {
            $queryBuilder = $queryBuilder->withUiTPASFilter($includeUiTPAS);
        }

        if ($request->query->get('creator')) {
            $queryBuilder = $queryBuilder->withCreatorFilter(
                new Creator($request->query->get('creator'))
            );
        }

        $createdFrom = $this->getDateTimeFromQuery($request, 'createdFrom');
        $createdTo = $this->getDateTimeFromQuery($request, 'createdTo');
        if ($createdFrom || $createdTo) {
            $queryBuilder = $queryBuilder->withCreatedRangeFilter($createdFrom, $createdTo);
        }

        $modifiedFrom = $this->getDateTimeFromQuery($request, 'modifiedFrom');
        $modifiedTo = $this->getDateTimeFromQuery($request, 'modifiedTo');
        if ($modifiedFrom || $modifiedTo) {
            $queryBuilder = $queryBuilder->withModifiedRangeFilter($modifiedFrom, $modifiedTo);
        }

        $calendarTypes = $this->getCalendarTypesFromQuery($request);
        if (!empty($calendarTypes)) {
            $queryBuilder = $queryBuilder->withCalendarTypeFilter(...$calendarTypes);
        }

        $dateFrom = $this->getDateTimeFromQuery($request, 'dateFrom');
        $dateTo = $this->getDateTimeFromQuery($request, 'dateTo');
        if ($dateFrom || $dateTo) {
            $queryBuilder = $queryBuilder->withDateRangeFilter($dateFrom, $dateTo);
        }

        $termIds = $this->getTermIdsFromQuery($request, 'termIds');
        foreach ($termIds as $termId) {
            $queryBuilder = $queryBuilder->withTermIdFilter($termId);
        }

        $termLabels = $this->getTermLabelsFromQuery($request, 'termLabels');
        foreach ($termLabels as $termLabel) {
            $queryBuilder = $queryBuilder->withTermLabelFilter($termLabel);
        }

        $locationTermIds = $this->getTermIdsFromQuery($request, 'locationTermIds');
        foreach ($locationTermIds as $locationTermId) {
            $queryBuilder = $queryBuilder->withLocationTermIdFilter($locationTermId);
        }

        $locationTermLabels = $this->getTermLabelsFromQuery($request, 'locationTermLabels');
        foreach ($locationTermLabels as $locationTermLabel) {
            $queryBuilder = $queryBuilder->withLocationTermLabelFilter($locationTermLabel);
        }

        $labels = $this->getLabelsFromQuery($request, 'labels');
        foreach ($labels as $label) {
            $queryBuilder = $queryBuilder->withLabelFilter($label);
        }

        $locationLabels = $this->getLabelsFromQuery($request, 'locationLabels');
        foreach ($locationLabels as $locationLabel) {
            $queryBuilder = $queryBuilder->withLocationLabelFilter($locationLabel);
        }

        $organizerLabels = $this->getLabelsFromQuery($request, 'organizerLabels');
        foreach ($organizerLabels as $organizerLabel) {
            $queryBuilder = $queryBuilder->withOrganizerLabelFilter($organizerLabel);
        }

        $facets = $this->getFacetsFromQuery($request, 'facets');
        foreach ($facets as $facet) {
            $queryBuilder = $queryBuilder->withFacet($facet);
        }

        $sorts = $this->getSortingFromQuery($request, 'sort');

        $sortBuilders = [
            'score' => function (OfferQueryBuilderInterface $queryBuilder, SortOrder $sortOrder) {
                return $queryBuilder->withSortByScore($sortOrder);
            },
            'availableTo' => function (OfferQueryBuilderInterface $queryBuilder, SortOrder $sortOrder) {
                return $queryBuilder->withSortByAvailableTo($sortOrder);
            },
            'distance' => function (OfferQueryBuilderInterface $queryBuilder, SortOrder $sortOrder) use ($coordinates) {
                if (!$coordinates) {
                    throw new \InvalidArgumentException(
                        'Required "coordinates" parameter missing when sorting by distance.'
                    );
                }

                return $queryBuilder->withSortByDistance($coordinates, $sortOrder);
            }
        ];

        foreach ($sorts as $field => $order) {
            if (!isset($sortBuilders[$field])) {
                throw new \InvalidArgumentException("Invalid sort field '{$field}' given.");
            }

            try {
                $sortOrder = SortOrder::get($order);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException("Invalid sort order '{$order}' given.");
            }

            $callback = $sortBuilders[$field];
            $queryBuilder = call_user_func($callback, $queryBuilder, $sortOrder);
        }

        $resultSet = $this->searchService->search($queryBuilder);

        $pagedCollection = $this->pagedCollectionFactory->fromPagedResultSet(
            $resultSet,
            $start,
            $limit
        );

        $jsonArray = $pagedCollection->jsonSerialize();

        foreach ($resultSet->getFacets() as $facetFilter) {
            // Singular "facet" to be consistent with "member" in Hydra
            // PagedCollection.
            $jsonArray['facet'][$facetFilter->getKey()] = $this->facetTreeNormalizer->normalize($facetFilter);
        }

        return (new JsonResponse($jsonArray, 200, ['Content-Type' => 'application/ld+json']))
            ->setPublic()
            ->setClientTtl(60 * 1)
            ->setTtl(60 * 5);
    }

    /**
     * @param mixed $mixed
     * @return bool|null
     */
    private function castMixedToBool($mixed)
    {
        if (is_null($mixed) || (is_string($mixed) && strlen($mixed) === 0)) {
            return null;
        }

        return filter_var($mixed, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return \DateTimeImmutable|null
     */
    private function getDateTimeFromQuery(Request $request, $queryParameter)
    {
        $asMixed = $request->query->get($queryParameter, null);

        if (is_null($asMixed)) {
            return null;
        }

        $asString = (string) $asMixed;

        $asDateTime = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $asString);

        if (!$asDateTime) {
            throw new \InvalidArgumentException(
                "{$queryParameter} should be an ISO-8601 datetime, for example 2017-04-26T12:20:05+01:00"
            );
        }

        return $asDateTime;
    }

    /**
     * @param Request $request
     * @param $queryParameter
     * @return \DateTimeImmutable|null
     */
    private function getAvailabilityFromQuery(Request $request, $queryParameter)
    {
        $defaultDateTime = \DateTimeImmutable::createFromFormat('U', $request->server->get('REQUEST_TIME'));
        $defaultDateTimeString = ($defaultDateTime) ? $defaultDateTime->format(\DateTime::ATOM) : null;

        return $this->getQueryParameterValue(
            $request,
            $queryParameter,
            $defaultDateTimeString,
            function ($dateTimeString) use ($queryParameter) {
                $dateTime = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $dateTimeString);

                if (!$dateTime) {
                    throw new \InvalidArgumentException(
                        "{$queryParameter} should be an ISO-8601 datetime, for example 2017-04-26T12:20:05+01:00"
                    );
                }

                return $dateTime;
            }
        );
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return TermId[]
     */
    private function getTermIdsFromQuery(Request $request, $queryParameter)
    {
        return $this->getArrayFromQueryParameters(
            $request,
            $queryParameter,
            function ($value) {
                return new TermId($value);
            }
        );
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return TermLabel[]
     */
    private function getTermLabelsFromQuery(Request $request, $queryParameter)
    {
        return $this->getArrayFromQueryParameters(
            $request,
            $queryParameter,
            function ($value) {
                return new TermLabel($value);
            }
        );
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return LabelName[]
     */
    private function getLabelsFromQuery(Request $request, $queryParameter)
    {
        return $this->getArrayFromQueryParameters(
            $request,
            $queryParameter,
            function ($value) {
                return new LabelName($value);
            }
        );
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return Language[]
     */
    private function getLanguagesFromQuery(Request $request, $queryParameter)
    {
        return $this->getArrayFromQueryParameters(
            $request,
            $queryParameter,
            function ($value) {
                return new Language($value);
            }
        );
    }

    /**
     * @param Request $request
     * @param $queryParameter
     * @return FacetName[]
     * @throws \InvalidArgumentException
     */
    private function getFacetsFromQuery(Request $request, $queryParameter)
    {
        return $this->getArrayFromQueryParameters(
            $request,
            $queryParameter,
            function ($value) {
                try {
                    return FacetName::fromNative(strtolower($value));
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("Unknown facet name '$value'.");
                }
            }
        );
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return RegionId[]
     */
    private function getRegionIdsFromQuery(Request $request, $queryParameter)
    {
        return $this->getArrayFromQueryParameters(
            $request,
            $queryParameter,
            function ($value) {
                return new RegionId($value);
            }
        );
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return SortOrder[]
     * @throws \InvalidArgumentException
     */
    private function getSortingFromQuery(Request $request, $queryParameter)
    {
        $sorting = $request->query->get($queryParameter, []);

        if (!is_array($sorting)) {
            throw new \InvalidArgumentException('Invalid sorting syntax given.');
        }

        return $sorting;
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @param callable|null $callback
     * @return array
     */
    private function getArrayFromQueryParameters(Request $request, $queryParameter, callable $callback = null)
    {
        if (empty($request->query->get($queryParameter))) {
            return [];
        }

        $values = (array) $request->query->get($queryParameter);

        if (!is_null($callback)) {
            $values = array_map($callback, $values);
        }

        return $values;
    }

    /**
     * @param Request $request
     * @return WorkflowStatus[]
     */
    private function getWorkflowStatusesFromQuery(Request $request)
    {
        return $this->getDelimitedQueryParameterValue(
            $request,
            'workflowStatus',
            'APPROVED,READY_FOR_VALIDATION',
            function ($workflowStatus) {
                return new WorkflowStatus($workflowStatus);
            }
        );
    }

    /**
     * @param Request $request
     * @return CalendarType[]
     */
    private function getCalendarTypesFromQuery(Request $request)
    {
        return $this->getDelimitedQueryParameterValue(
            $request,
            'calendarType',
            null,
            function ($calendarType) {
                return new CalendarType($calendarType);
            }
        );
    }

    /**
     * @param Request $request
     * @return Country|null
     */
    private function getAddressCountryFromQuery(Request $request)
    {
        return $this->getQueryParameterValue(
            $request,
            'addressCountry',
            'BE',
            function ($country) {
                try {
                    $countryCode = CountryCode::fromNative(strtoupper((string) $country));
                    return new Country($countryCode);
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("Unknown country code '{$country}'.");
                }
            }
        );
    }

    /**
     * @param Request $request
     * @return AudienceType|null
     */
    private function getAudienceTypeFromQuery(Request $request)
    {
        return $this->getQueryParameterValue(
            $request,
            'audienceType',
            'everyone',
            function ($audienceType) {
                return new AudienceType($audienceType);
            }
        );
    }

    /**
     * @param Request $request
     * @param string $parameterName
     * @param mixed|null $defaultValue
     * @param callable $callback
     * @return mixed|null
     */
    private function getQueryParameterValue(
        Request $request,
        $parameterName,
        $defaultValue = null,
        callable $callback = null
    ) {
        $parameterValue = $request->query->get($parameterName, null);
        $defaultsEnabled = $this->defaultFiltersAreEnabled($request);
        $callback = $this->ensureCallback($callback);

        if ($parameterValue === OfferSearchController::QUERY_PARAMETER_RESET_VALUE ||
            is_null($parameterValue) && (is_null($defaultValue) || !$defaultsEnabled)) {
            return null;
        }

        if (is_null($parameterValue)) {
            $parameterValue = $defaultValue;
        }

        return call_user_func($callback, $parameterValue);
    }

    /**
     * @param Request $request
     * @param $parameterName
     * @param mixed|null $defaultValue
     * @param callable $callback
     * @param string $delimiter
     * @return array
     */
    private function getDelimitedQueryParameterValue(
        Request $request,
        $parameterName,
        $defaultValue = null,
        callable $callback = null,
        $delimiter = ','
    ) {
        $callback = $this->ensureCallback($callback);

        $asString = $this->getQueryParameterValue(
            $request,
            $parameterName,
            $defaultValue
        );

        if (is_null($asString)) {
            return [];
        }

        $asArray = explode($delimiter, $asString);

        return array_map($callback, $asArray);
    }

    /**
     * @param Request $request
     * @return bool
     */
    private function defaultFiltersAreEnabled(Request $request)
    {
        $disabled = $this->castMixedToBool($request->query->get('disableDefaultFilters', false));
        return !$disabled;
    }

    /**
     * @param callable|null $callback
     * @return callable
     */
    private function ensureCallback(callable $callback = null)
    {
        if (!is_null($callback)) {
            return $callback;
        }

        $passthroughCallback = function ($value) {
            return $value;
        };

        return $passthroughCallback;
    }
}
