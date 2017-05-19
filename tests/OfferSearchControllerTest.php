<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Geocoding\Coordinate\Coordinates;
use CultuurNet\Geocoding\Coordinate\Latitude;
use CultuurNet\Geocoding\Coordinate\Longitude;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\Creator;
use CultuurNet\UDB3\Search\ElasticSearch\Offer\ElasticSearchOfferQueryBuilder;
use CultuurNet\UDB3\Search\Facet\FacetFilter;
use CultuurNet\UDB3\Search\Facet\FacetNode;
use CultuurNet\UDB3\Search\GeoDistanceParameters;
use CultuurNet\UDB3\Search\Offer\AudienceType;
use CultuurNet\UDB3\Search\Offer\CalendarType;
use CultuurNet\UDB3\Search\Offer\Cdbid;
use CultuurNet\UDB3\Search\Offer\FacetName;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use CultuurNet\UDB3\Search\Offer\OfferSearchParameters;
use CultuurNet\UDB3\Search\Offer\OfferSearchServiceInterface;
use CultuurNet\UDB3\Search\Offer\SortBy;
use CultuurNet\UDB3\Search\Offer\Sorting;
use CultuurNet\UDB3\Search\Offer\WorkflowStatus;
use CultuurNet\UDB3\Search\Offer\TermId;
use CultuurNet\UDB3\Search\Offer\TermLabel;
use CultuurNet\UDB3\Search\PagedResultSet;
use CultuurNet\UDB3\Search\Region\RegionId;
use CultuurNet\UDB3\Search\SortOrder;
use CultuurNet\UDB3\ValueObject\MultilingualString;
use Symfony\Component\HttpFoundation\Request;
use ValueObjects\Geography\Country;
use ValueObjects\Geography\CountryCode;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;

class OfferSearchControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ElasticSearchOfferQueryBuilder
     */
    private $queryBuilder;

    /**
     * @var OfferSearchServiceInterface|\PHPUnit_Framework_MockObject_MockObject
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
     * @var MockQueryStringFactory
     */
    private $queryStringFactory;

    /**
     * @var MockDistanceFactory
     */
    private $distanceFactory;

    /**
     * @var NodeAwareFacetTreeNormalizer
     */
    private $facetTreeNormalizer;

    /**
     * @var OfferSearchController
     */
    private $controller;

    public function setUp()
    {
        $this->queryBuilder = new ElasticSearchOfferQueryBuilder();
        $this->searchService = $this->createMock(OfferSearchServiceInterface::class);

        $this->regionIndexName = new StringLiteral('geoshapes');
        $this->regionDocumentType = new StringLiteral('region');

        $this->queryStringFactory = new MockQueryStringFactory();
        $this->distanceFactory = new MockDistanceFactory();

        $this->facetTreeNormalizer = new NodeAwareFacetTreeNormalizer();

        $this->controller = new OfferSearchController(
            $this->queryBuilder,
            $this->searchService,
            $this->regionIndexName,
            $this->regionDocumentType,
            $this->queryStringFactory,
            $this->distanceFactory,
            $this->facetTreeNormalizer
        );
    }

    /**
     * @test
     */
    public function it_returns_a_paged_collection_of_search_results_based_on_request_query_parameters()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'start' => 30,
                'limit' => 10,
                'q' => 'dag van de fiets AND labels:foo',
                'text' => '(foo OR bar) AND baz',
                'id' => '42926044-09f4-4bd5-bc35-427b2fc1a525',
                'locationId' => '652ab95e-fdff-41ce-8894-1b29dce0d230',
                'organizerId' => '392168d7-57c9-4488-8e2e-d492c843054b',
                'availableFrom' => '2017-04-26T00:00:00+01:00',
                'availableTo' => '2017-04-28T15:30:23+01:00',
                'workflowStatus' => 'DRAFT',
                'regions' => ['gem-leuven', 'prv-limburg'],
                'coordinates' => '-40,70',
                'distance' => '30km',
                'postalCode' => 3000,
                'addressCountry' => 'BE',
                'minAge' => 3,
                'maxAge' => 7,
                'price' => 1.55,
                'minPrice' => 0.99,
                'maxPrice' => 1.99,
                'audienceType' => 'members',
                'hasMediaObjects' => 'true',
                'labels' => ['foo', 'bar'],
                'locationLabels' => ['lorem'],
                'organizerLabels' => ['ipsum'],
                'textLanguages' => ['nl', 'en'],
                'languages' => ['nl', 'en', 'fr'],
                'calendarType' => 'single',
                'dateFrom' => '2017-05-01T00:00:00+01:00',
                'dateTo' => '2017-05-01T23:59:59+01:00',
                'createdFrom' => '2017-05-01T13:33:37+01:00',
                'createdTo' => '2017-05-01T13:33:37+01:00',
                'modifiedFrom' => '2017-05-01T13:33:37+01:00',
                'modifiedTo' => '2017-05-01T13:33:37+01:00',
                'termIds' => ['1.45.678.95', 'azYBznHY'],
                'termLabels' => ['Jeugdhuis', 'Cultureel centrum'],
                'locationTermIds' => ['1234', '5678'],
                'uitpas' => 'true',
                'locationTermLabels' => ['foo1', 'bar1'],
                'organizerTermIds' => ['9012', '3456'],
                'organizerTermLabels' => ['foo2', 'bar2'],
                'facets' => ['regions'],
                'creator' => 'Jane Doe',
                'sort' => [
                    'availableTo' => 'asc',
                    'score' => 'desc',
                ],
            ]
        );

        /* @var OfferQueryBuilderInterface $expectedQueryBuilder */
        $expectedQueryBuilder = $this->queryBuilder;
        $expectedQueryBuilder = $expectedQueryBuilder->withAdvancedQuery(
                new MockQueryString('dag van de fiets AND labels:foo'),
                new Language('nl'),
                new Language('en')
            )
            ->withTextQuery(
                new StringLiteral('(foo OR bar) AND baz'),
                new Language('nl'),
                new Language('en')
            )
            ->withLanguageFilter(new Language('nl'))
            ->withLanguageFilter(new Language('en'))
            ->withLanguageFilter(new Language('fr'))
            ->withCdbIdFilter(
                new Cdbid('42926044-09f4-4bd5-bc35-427b2fc1a525')
            )
            ->withLocationCdbIdFilter(
                new Cdbid('652ab95e-fdff-41ce-8894-1b29dce0d230')
            )
            ->withOrganizerCdbidFilter(
                new Cdbid('392168d7-57c9-4488-8e2e-d492c843054b')
            )
            ->withWorkflowStatusFilter(
                new WorkflowStatus('DRAFT')
            )
            ->withAvailableRangeFilter(
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-04-26T00:00:00+01:00'),
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-04-28T15:30:23+01:00')
            )
            ->withRegionFilter(
                $this->regionIndexName,
                $this->regionDocumentType,
                new RegionId('gem-leuven')
            )
            ->withRegionFilter(
                $this->regionIndexName,
                $this->regionDocumentType,
                new RegionId('prv-limburg')
            )
            ->withGeoDistanceFilter(
                new GeoDistanceParameters(
                    new Coordinates(
                        new Latitude(-40.0),
                        new Longitude(70.0)
                    ),
                    new MockDistance('30km')
                )
            )
            ->withPostalCodeFilter(new PostalCode("3000"))
            ->withAddressCountryFilter(new Country(CountryCode::fromNative('BE')))
            ->withAudienceTypeFilter(new AudienceType('members'))
            ->withAgeRangeFilter(new Natural(3), new Natural(7))
            ->withPriceRangeFilter(Price::fromFloat(1.55), Price::fromFloat(1.55))
            ->withMediaObjectsFilter(true)
            ->withUiTPASFilter(true)
            ->withCreatorFilter(new Creator('Jane Doe'))
            ->withCreatedRangeFilter(
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-05-01T13:33:37+01:00'),
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-05-01T13:33:37+01:00')
            )
            ->withModifiedRangeFilter(
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-05-01T13:33:37+01:00'),
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-05-01T13:33:37+01:00')
            )
            ->withCalendarTypeFilter(new CalendarType('single'))
            ->withDateRangeFilter(
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-05-01T00:00:00+01:00'),
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-05-01T23:59:59+01:00')
            )
            ->withTermIdFilter(new TermId('1.45.678.95'))
            ->withTermIdFilter(new TermId('azYBznHY'))
            ->withTermLabelFilter(new TermLabel('Jeugdhuis'))
            ->withTermLabelFilter(new TermLabel('Cultureel centrum'))
            ->withLocationTermIdFilter(new TermId('1234'))
            ->withLocationTermIdFilter(new TermId('5678'))
            ->withLocationTermLabelFilter(new TermLabel('foo1'))
            ->withLocationTermLabelFilter(new TermLabel('bar1'))
            ->withLabelFilter(new LabelName('foo'))
            ->withLabelFilter(new LabelName('bar'))
            ->withLocationLabelFilter(new LabelName('lorem'))
            ->withOrganizerLabelFilter(new LabelName('ipsum'))
            ->withFacet(FacetName::REGIONS())
            ->withSort(SortBy::AVAILABLE_TO(), SortOrder::ASC())
            ->withSort(SortBy::SCORE(), SortOrder::DESC())
            ->withStart(new Natural(30))
            ->withLimit(new Natural(10));

        $expectedResultSet = new PagedResultSet(
            new Natural(32),
            new Natural(10),
            [
                new JsonDocument('3f2ba18c-59a9-4f65-a242-462ad467c72b', '{"@id": "events/1"}'),
                new JsonDocument('39d06346-b762-4ccd-8b3a-142a8f6abbbe', '{"@id": "places/2"}'),
            ]
        );

        $expectedResultSet = $expectedResultSet->withFacets(
            new FacetFilter(
                'regions',
                [
                    new FacetNode(
                        'gem-leuven',
                        new MultilingualString(new Language('nl'), new StringLiteral('Leuven')),
                        7,
                        [
                            new FacetNode(
                                'gem-wijgmaal',
                                new MultilingualString(new Language('nl'), new StringLiteral('Wijgmaal')),
                                3
                            ),
                        ]
                    ),
                ]
            )
        );

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $expectedJsonResponse = json_encode(
            [
                '@context' => 'http://www.w3.org/ns/hydra/context.jsonld',
                '@type' => 'PagedCollection',
                'itemsPerPage' => 10,
                'totalItems' => 32,
                'member' => [
                    ['@id' => 'events/1'],
                    ['@id' => 'places/2'],
                ],
                'facet' => [
                    'regions' => [
                        'gem-leuven' => [
                            'name' => [
                                'nl' => 'Leuven'
                            ],
                            'count' => 7,
                            'children' => [
                                'gem-wijgmaal' => [
                                    'name' => [
                                        'nl' => 'Wijgmaal',
                                    ],
                                    'count' => 3,
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $actualJsonResponse = $this->controller->search($request)
            ->getContent();

        $this->assertEquals($expectedJsonResponse, $actualJsonResponse);
    }

    /**
     * @test
     */
    public function it_uses_the_default_limit_of_30_if_a_limit_of_0_is_given()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'start' => 0,
                'limit' => 0,
                'availableFrom' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'availableTo' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder
            ->withStart(new Natural(0))
            ->withLimit(new Natural(30));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_sets_a_default_available_from_and_available_to_if_none_are_given()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [],
            [],
            [],
            ['REQUEST_TIME' => 1493195661]
        );

        $expectedQueryBuilder = $this->queryBuilder
            ->withAvailableRangeFilter(
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-04-26T08:34:21+00:00'),
                \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-04-26T08:34:21+00:00')
            );

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_expects_a_valid_available_from_and_available_to_date()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'start' => 0,
                'limit' => 0,
                'availableFrom' => '2017-04-01',
                'availableTo' => '2017-04-01',
            ]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'availableFrom should be an ISO-8601 datetime, for example 2017-04-26T12:20:05+01:00'
        );

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_if_coordinates_is_given_without_distance()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            ['coordinates' => '-40,70']
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Required "distance" parameter missing when searching by coordinates.');

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_if_distance_is_given_without_coordinates()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            ['distance' => '30km']
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Required "coordinates" parameter missing when searching by distance.');

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_works_with_a_min_age_of_zero_and_or_a_max_age_of_zero()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'start' => 0,
                'limit' => 0,
                'availableFrom' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'availableTo' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'minAge' => 0,
                'maxAge' => 0,
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder
            ->withStart(new Natural(0))
            ->withLimit(new Natural(30))
            ->withAgeRangeFilter(new Natural(0), new Natural(0));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_works_with_a_min_price_and_max_price_if_no_exact_price_is_set()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'start' => 0,
                'limit' => 0,
                'availableFrom' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'availableTo' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'minPrice' => 0.14,
                'maxPrice' => 2.24,
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder
            ->withStart(new Natural(0))
            ->withLimit(new Natural(30))
            ->withPriceRangeFilter(Price::fromFloat(0.14), Price::fromFloat(2.24));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     * @dataProvider booleanStringDataProvider
     *
     * @param string $stringValue
     * @param bool|null $booleanValue
     */
    public function it_converts_the_media_objects_toggle_parameter_to_a_correct_boolean(
        $stringValue,
        $booleanValue
    ) {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'hasMediaObjects' => $stringValue,
                'availableFrom' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'availableTo' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder;

        if (!is_null($booleanValue)) {
            $expectedQueryBuilder = $expectedQueryBuilder
                ->withMediaObjectsFilter($booleanValue);
        }

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     * @dataProvider booleanStringDataProvider
     *
     * @param string $stringValue
     * @param bool|null $booleanValue
     */
    public function it_converts_the_uitpas_toggle_parameter_to_a_correct_boolean(
        $stringValue,
        $booleanValue
    ) {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'uitpas' => $stringValue,
                'availableFrom' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'availableTo' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder;

        if (!is_null($booleanValue)) {
            $expectedQueryBuilder = $expectedQueryBuilder
                ->withUiTPASFilter($booleanValue);
        }

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @return Request[]
     */
    public function booleanStringDataProvider()
    {
        return [
            [
                false,
                false,
            ],
            [
                true,
                true,
            ],
            [
                'false',
                false,
            ],
            [
                'FALSE',
                false,
            ],
            [
                '0',
                false,
            ],
            [
                0,
                false,
            ],
            [
                'true',
                true,
            ],
            [
                'TRUE',
                true,
            ],
            [
                '1',
                true,
            ],
            [
                1,
                true,
            ],
            [
                '',
                null,
            ],
            [
                null,
                null,
            ],
        ];
    }

    /**
     * @test
     */
    public function it_can_handle_a_single_string_value_for_parameters_that_are_normally_arrays()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'start' => 30,
                'limit' => 10,
                'availableFrom' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'availableTo' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'labels' => 'foo',
                'organizerLabels' => 'bar',
                'locationLabels' => 'baz',
                'text' => 'foobar',
                'textLanguages' => 'nl',
                'languages' => 'nl',
                'termIds' => '0.145.567.6',
                'termLabels' => 'Jeugdhuis',
                'facets' => 'regions',
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder
            ->withStart(new Natural(30))
            ->withLimit(new Natural(10))
            ->withTextQuery(new StringLiteral('foobar'), new Language('nl'))
            ->withLanguageFilter(new Language('nl'))
            ->withTermIdFilter(new TermId('0.145.567.6'))
            ->withTermLabelFilter(new TermLabel('Jeugdhuis'))
            ->withLabelFilter(new LabelName('foo'))
            ->withLocationLabelFilter(new LabelName('baz'))
            ->withOrganizerLabelFilter(new LabelName('bar'))
            ->withFacet(FacetName::REGIONS());

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_an_unknown_facet_name_is_given()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown facet name 'bla'.");
        $request = Request::create('http://search.uitdatabank.be/offers/', 'GET', ['facets' => ['regions', 'bla']]);
        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_an_unknown_address_country_is_given()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown country code 'foobar'.");
        $request = Request::create('http://search.uitdatabank.be/offers/', 'GET', ['addressCountry' => 'foobar']);
        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_transforms_the_request_address_country_to_uppercase()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'availableFrom' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'availableTo' => OfferSearchController::QUERY_PARAMETER_RESET_VALUE,
                'addressCountry' => 'nl'
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder
            ->withAddressCountryFilter(new Country(CountryCode::fromNative('NL')));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     * @dataProvider malformedDateTimeProvider
     *
     * @param string $malformedDateTimeAsString
     */
    public function it_throws_an_exception_for_a_malformed_date_from(
        $malformedDateTimeAsString
    ) {
        $request = new Request(['dateFrom' => $malformedDateTimeAsString]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dateFrom should be an ISO-8601 datetime, for example 2017-04-26T12:20:05+01:00');
        $this->controller->search($request);
    }

    /**
     * @return array
     */
    public function malformedDateTimeProvider()
    {
        return [
            ['2017'],
            ['2017-01'],
            ['2017-01-01'],
            ['2017-01-01T'],
            ['2017-01-01T23'],
            ['2017-01-01T23:59'],
            ['2017-01-01T23:59:59'],
            [false],
            [true],
            [0],
            [1],
            ['now'],
            ['1493726880'],
        ];
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_sort_is_not_an_array()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'sort' => 'availableTo asc'
            ]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sorting syntax given.');

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_a_sort_field_is_invalid()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'sort' => [
                    'availableTo' => 'asc',
                    'name.nl' => 'asc',
                    'score' => 'desc',
                ]
            ]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid sort field 'name.nl' given.");

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_a_sort_order_is_invalid()
    {
        $request = Request::create(
            'http://search.uitdatabank.be/offers/',
            'GET',
            [
                'sort' => [
                    'availableTo' => 'ascending',
                    'score' => 'descending',
                ]
            ]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid sort order 'ascending' given.");

        $this->controller->search($request);
    }

    /**
     * @test
     * @dataProvider unknownParameterProvider
     *
     * @param Request $request
     * @param string $expectedExceptionMessage
     */
    public function it_rejects_queries_with_unknown_parameters(
        Request $request,
        $expectedExceptionMessage
    ) {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->controller->search($request);
    }

    public function unknownParameterProvider()
    {
        return [
            'single unknown parameter' => [
                'request' => Request::create(
                    'http://search.uitdatabank.be/offers/',
                    'GET',
                    [
                        'fat' => [
                            'lip',
                        ],
                    ]
                ),
                'expectedExceptionMessage' => 'Unknown query parameter(s): fat'
            ],
            'multiple unknown parameter' => [
                'request' => Request::create(
                    'http://search.uitdatabank.be/offers/',
                    'GET',
                    [
                        'fat' => [
                            'lip',
                        ],
                        'bat' => [
                            'cave',
                        ],
                    ]
                ),
                'expectedExceptionMessage' => 'Unknown query parameter(s): fat, bat'
            ],
            'unknown and whitelisted parameter' => [
                'request' => Request::create(
                    'http://search.uitdatabank.be/offers/',
                    'GET',
                    [
                        'id' => [
                            '5333ED41-91FA-43F4-82BA-F28A9AC96A6E',
                        ],
                        'bat' => [
                            'cave',
                        ],
                    ]
                ),
                'expectedExceptionMessage' => 'Unknown query parameter(s): bat'
            ],
        ];
    }

    /**
     * @param OfferQueryBuilderInterface $expectedQueryBuilder
     * @param PagedResultSet $pagedResultSet
     */
    private function expectQueryBuilderWillReturnResultSet(
        OfferQueryBuilderInterface $expectedQueryBuilder,
        PagedResultSet $pagedResultSet
    ) {
        $this->searchService->expects($this->once())
            ->method('search')
            ->with(
                $this->callback(
                    function ($actualQueryBuilder) use ($expectedQueryBuilder) {
                        $this->assertEquals(
                            $expectedQueryBuilder->build()->toArray(),
                            $actualQueryBuilder->build()->toArray()
                        );
                        return true;
                    }
                )
            )
            ->willReturn($pagedResultSet);
    }
}
