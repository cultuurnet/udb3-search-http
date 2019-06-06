<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Geocoding\Coordinate\Coordinates;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\ApiGuard\ApiKey\Reader\ApiKeyReaderInterface;
use CultuurNet\UDB3\ApiGuard\Consumer\ConsumerReadRepositoryInterface;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\Search\Creator;
use CultuurNet\UDB3\Search\DistanceFactoryInterface;
use CultuurNet\UDB3\Search\GeoDistanceParameters;
use CultuurNet\UDB3\Search\Http\Offer\RequestParser\OfferRequestParserInterface;
use CultuurNet\UDB3\Search\Http\Parameters\OfferParameterWhiteList;
use CultuurNet\UDB3\Search\Http\Parameters\ParameterBagInterface;
use CultuurNet\UDB3\Search\Http\Parameters\SymfonyParameterBagAdapter;
use CultuurNet\UDB3\Search\JsonDocument\PassThroughJsonDocumentTransformer;
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
use ValueObjects\Geography\CountryCode;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;

/**
 * @todo Extract more parsing functionality to OfferRequestParserInterface
 *   implementations.
 * @see https://jira.uitdatabank.be/browse/III-2144
 */
class OfferSearchController
{
    /**
     * @var ApiKeyReaderInterface
     */
    private $apiKeyReader;

    /**
     * @var ConsumerReadRepositoryInterface
     */
    private $consumerReadRepository;

    /**
     * @var OfferQueryBuilderInterface
     */
    private $queryBuilder;

    /**
     * @var OfferRequestParserInterface
     */
    private $requestParser;

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
     * @param ApiKeyReaderInterface $apiKeyReader
     * @param ConsumerReadRepositoryInterface $consumerReadRepository
     * @param OfferQueryBuilderInterface $queryBuilder
     * @param OfferRequestParserInterface $offerRequestParser
     * @param OfferSearchServiceInterface $searchService
     * @param StringLiteral $regionIndexName
     * @param StringLiteral $regionDocumentType
     * @param QueryStringFactoryInterface $queryStringFactory
     * @param FacetTreeNormalizerInterface $facetTreeNormalizer
     * @param PagedCollectionFactoryInterface|null $pagedCollectionFactory
     */
    public function __construct(
        ApiKeyReaderInterface $apiKeyReader,
        ConsumerReadRepositoryInterface $consumerReadRepository,
        OfferQueryBuilderInterface $queryBuilder,
        OfferRequestParserInterface $offerRequestParser,
        OfferSearchServiceInterface $searchService,
        StringLiteral $regionIndexName,
        StringLiteral $regionDocumentType,
        QueryStringFactoryInterface $queryStringFactory,
        FacetTreeNormalizerInterface $facetTreeNormalizer,
        PagedCollectionFactoryInterface $pagedCollectionFactory = null
    ) {
        if (is_null($pagedCollectionFactory)) {
            $pagedCollectionFactory = new ResultTransformingPagedCollectionFactory(
                new PassThroughJsonDocumentTransformer()
            );
        }

        $this->apiKeyReader = $apiKeyReader;
        $this->consumerReadRepository = $consumerReadRepository;
        $this->queryBuilder = $queryBuilder;
        $this->requestParser = $offerRequestParser;
        $this->searchService = $searchService;
        $this->regionIndexName = $regionIndexName;
        $this->regionDocumentType = $regionDocumentType;
        $this->queryStringFactory = $queryStringFactory;
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

        $parameterBag = new SymfonyParameterBagAdapter($request->query);

        $textLanguages = $this->getLanguagesFromQuery($parameterBag, 'textLanguages');

        $consumerApiKey = $this->apiKeyReader->read($request);
        $consumer = $consumerApiKey ? $this->consumerReadRepository->getConsumer($consumerApiKey) : null;
        $defaultQuery = $consumer ? $consumer->getDefaultQuery() : null;
        if ($defaultQuery) {
            $queryBuilder = $queryBuilder->withAdvancedQuery(
                $this->queryStringFactory->fromString(
                    $defaultQuery->toNative()
                ),
                ...$textLanguages
            );
        }

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

        $availableFrom = $this->getAvailabilityFromQuery($request, 'availableFrom');
        $availableTo = $this->getAvailabilityFromQuery($request, 'availableTo');
        if ($availableFrom || $availableTo) {
            $queryBuilder = $queryBuilder->withAvailableRangeFilter($availableFrom, $availableTo);
        }

        $regionIds = $this->getRegionIdsFromQuery($parameterBag, 'regions');
        foreach ($regionIds as $regionId) {
            $queryBuilder = $queryBuilder->withRegionFilter(
                $this->regionIndexName,
                $this->regionDocumentType,
                $regionId
            );
        }

        $postalCode = (string) $request->query->get('postalCode');
        if (!empty($postalCode)) {
            $queryBuilder = $queryBuilder->withPostalCodeFilter(
                new PostalCode($postalCode)
            );
        }

        $country = (new CountryExtractor())->getCountryFromQuery(
            $parameterBag,
            CountryCode::fromNative('BE')
        );
        if (!empty($country)) {
            $queryBuilder = $queryBuilder->withAddressCountryFilter($country);
        }

        $audienceType = $this->getAudienceTypeFromQuery($parameterBag);
        if (!empty($audienceType)) {
            $queryBuilder = $queryBuilder->withAudienceTypeFilter($audienceType);
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

        $includeMediaObjects = $parameterBag->getBooleanFromParameter('hasMediaObjects');
        if (!is_null($includeMediaObjects)) {
            $queryBuilder = $queryBuilder->withMediaObjectsFilter($includeMediaObjects);
        }

        $includeUiTPAS = $parameterBag->getBooleanFromParameter('uitpas');
        if (!is_null($includeUiTPAS)) {
            $queryBuilder = $queryBuilder->withUiTPASFilter($includeUiTPAS);
        }

        if ($request->query->get('creator')) {
            $queryBuilder = $queryBuilder->withCreatorFilter(
                new Creator($request->query->get('creator'))
            );
        }

        $createdFrom = $parameterBag->getDateTimeFromParameter('createdFrom');
        $createdTo = $parameterBag->getDateTimeFromParameter('createdTo');
        if ($createdFrom || $createdTo) {
            $queryBuilder = $queryBuilder->withCreatedRangeFilter($createdFrom, $createdTo);
        }

        $modifiedFrom = $parameterBag->getDateTimeFromParameter('modifiedFrom');
        $modifiedTo = $parameterBag->getDateTimeFromParameter('modifiedTo');
        if ($modifiedFrom || $modifiedTo) {
            $queryBuilder = $queryBuilder->withModifiedRangeFilter($modifiedFrom, $modifiedTo);
        }

        $calendarTypes = $this->getCalendarTypesFromQuery($parameterBag);
        if (!empty($calendarTypes)) {
            $queryBuilder = $queryBuilder->withCalendarTypeFilter(...$calendarTypes);
        }

        $dateFrom = $parameterBag->getDateTimeFromParameter('dateFrom');
        $dateTo = $parameterBag->getDateTimeFromParameter('dateTo');
        if ($dateFrom || $dateTo) {
            $queryBuilder = $queryBuilder->withDateRangeFilter($dateFrom, $dateTo);
        }

        $termIds = $this->getTermIdsFromQuery($parameterBag, 'termIds');
        foreach ($termIds as $termId) {
            $queryBuilder = $queryBuilder->withTermIdFilter($termId);
        }

        $termLabels = $this->getTermLabelsFromQuery($parameterBag, 'termLabels');
        foreach ($termLabels as $termLabel) {
            $queryBuilder = $queryBuilder->withTermLabelFilter($termLabel);
        }

        $locationTermIds = $this->getTermIdsFromQuery($parameterBag, 'locationTermIds');
        foreach ($locationTermIds as $locationTermId) {
            $queryBuilder = $queryBuilder->withLocationTermIdFilter($locationTermId);
        }

        $locationTermLabels = $this->getTermLabelsFromQuery($parameterBag, 'locationTermLabels');
        foreach ($locationTermLabels as $locationTermLabel) {
            $queryBuilder = $queryBuilder->withLocationTermLabelFilter($locationTermLabel);
        }

        $labels = $this->getLabelsFromQuery($parameterBag, 'labels');
        foreach ($labels as $label) {
            $queryBuilder = $queryBuilder->withLabelFilter($label);
        }

        $locationLabels = $this->getLabelsFromQuery($parameterBag, 'locationLabels');
        foreach ($locationLabels as $locationLabel) {
            $queryBuilder = $queryBuilder->withLocationLabelFilter($locationLabel);
        }

        $organizerLabels = $this->getLabelsFromQuery($parameterBag, 'organizerLabels');
        foreach ($organizerLabels as $organizerLabel) {
            $queryBuilder = $queryBuilder->withOrganizerLabelFilter($organizerLabel);
        }

        $facets = $this->getFacetsFromQuery($parameterBag, 'facets');
        foreach ($facets as $facet) {
            $queryBuilder = $queryBuilder->withFacet($facet);
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
     * @param Request $request
     * @param $queryParameter
     * @return \DateTimeImmutable|null
     */
    private function getAvailabilityFromQuery(Request $request, $queryParameter)
    {
        $defaultDateTime = \DateTimeImmutable::createFromFormat('U', $request->server->get('REQUEST_TIME'));
        $defaultDateTimeString = ($defaultDateTime) ? $defaultDateTime->format(\DateTime::ATOM) : null;

        $parameterBag = new SymfonyParameterBagAdapter($request->query);

        return $parameterBag->getStringFromParameter(
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
     * @param ParameterBagInterface $parameterBag
     * @param string $queryParameter
     * @return TermId[]
     */
    private function getTermIdsFromQuery(ParameterBagInterface $parameterBag, $queryParameter)
    {
        return $parameterBag->getArrayFromParameter(
            $queryParameter,
            function ($value) {
                return new TermId($value);
            }
        );
    }

    /**
     * @param ParameterBagInterface $parameterBag
     * @param string $queryParameter
     * @return TermLabel[]
     */
    private function getTermLabelsFromQuery(ParameterBagInterface $parameterBag, $queryParameter)
    {
        return $parameterBag->getArrayFromParameter(
            $queryParameter,
            function ($value) {
                return new TermLabel($value);
            }
        );
    }

    /**
     * @param ParameterBagInterface $parameterBag
     * @param string $queryParameter
     * @return LabelName[]
     */
    private function getLabelsFromQuery(ParameterBagInterface $parameterBag, $queryParameter)
    {
        return $parameterBag->getArrayFromParameter(
            $queryParameter,
            function ($value) {
                return new LabelName($value);
            }
        );
    }

    /**
     * @param ParameterBagInterface $parameterBag
     * @param string $queryParameter
     * @return Language[]
     */
    private function getLanguagesFromQuery(ParameterBagInterface $parameterBag, $queryParameter)
    {
        return $parameterBag->getArrayFromParameter(
            $queryParameter,
            function ($value) {
                return new Language($value);
            }
        );
    }

    /**
     * @param ParameterBagInterface $parameterBag
     * @param string $queryParameter
     * @return RegionId[]
     */
    private function getRegionIdsFromQuery(ParameterBagInterface $parameterBag, $queryParameter)
    {
        return $parameterBag->getArrayFromParameter(
            $queryParameter,
            function ($value) {
                return new RegionId($value);
            }
        );
    }

    /**
     * @param ParameterBagInterface $parameterBag
     * @return CalendarType[]
     */
    private function getCalendarTypesFromQuery(ParameterBagInterface $parameterBag)
    {
        return $parameterBag->getExplodedStringFromParameter(
            'calendarType',
            null,
            function ($calendarType) {
                return new CalendarType($calendarType);
            }
        );
    }

    /**
     * @param ParameterBagInterface $parameterBag
     * @return AudienceType|null
     */
    private function getAudienceTypeFromQuery(ParameterBagInterface $parameterBag)
    {
        return $parameterBag->getStringFromParameter(
            'audienceType',
            'everyone',
            function ($audienceType) {
                return new AudienceType($audienceType);
            }
        );
    }

    /**
     * @param ParameterBagInterface $parameterBag
     * @param $queryParameter
     * @return FacetName[]
     */
    private function getFacetsFromQuery(ParameterBagInterface $parameterBag, $queryParameter)
    {
        return $parameterBag->getArrayFromParameter(
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
}
