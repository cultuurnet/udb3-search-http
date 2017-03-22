<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\Offer\OfferSearchParameters;
use CultuurNet\UDB3\Search\Offer\OfferSearchServiceInterface;
use CultuurNet\UDB3\Search\PagedResultSet;
use CultuurNet\UDB3\Search\Region\RegionId;
use Symfony\Component\HttpFoundation\Request;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;

class OfferSearchControllerTest extends \PHPUnit_Framework_TestCase
{
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
     * @var OfferSearchController
     */
    private $controller;

    public function setUp()
    {
        $this->searchService = $this->createMock(OfferSearchServiceInterface::class);

        $this->regionIndexName = new StringLiteral('geoshapes');
        $this->regionDocumentType = new StringLiteral('region');

        $this->queryStringFactory = new MockQueryStringFactory();

        $this->controller = new OfferSearchController(
            $this->searchService,
            $this->regionIndexName,
            $this->regionDocumentType,
            $this->queryStringFactory
        );
    }

    /**
     * @test
     */
    public function it_returns_a_paged_collection_of_search_results_based_on_request_query_parameters()
    {
        $request = new Request(
            [
                'start' => 30,
                'limit' => 10,
                'q' => 'dag van de fiets',
                'regionId' => 'gem-leuven',
                'minAge' => 3,
                'maxAge' => 7,
                'price' => 1.55,
                'minPrice' => 0.99,
                'maxPrice' => 1.99,
                'labels' => ['foo', 'bar'],
                'locationLabels' => ['lorem'],
                'organizerLabels' => ['ipsum'],
                'textLanguages' => ['nl', 'en'],
            ]
        );

        $expectedSearchParameters = (new OfferSearchParameters())
            ->withQueryString(
                new MockQueryString('dag van de fiets')
            )
            ->withRegion(
                new RegionId('gem-leuven'),
                $this->regionIndexName,
                $this->regionDocumentType
            )
            ->withMinimumAge(new Natural(3))
            ->withMaximumAge(new Natural(7))
<<<<<<< HEAD
            ->withPrice(Price::fromFloat(1.55))
            ->withMinimumPrice(Price::fromFloat(0.99))
            ->withMaximumPrice(Price::fromFloat(1.99))
=======
            ->withTextLanguages(
                new Language('nl'),
                new Language('en')
            )
>>>>>>> master
            ->withLabels(
                new LabelName('foo'),
                new LabelName('bar')
            )
            ->withLocationLabels(
                new LabelName('lorem')
            )
            ->withOrganizerLabels(
                new LabelName('ipsum')
            )
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

        $this->searchService->expects($this->once())
            ->method('search')
            ->with($expectedSearchParameters)
            ->willReturn($expectedResultSet);

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
        $request = new Request(
            [
                'start' => 0,
                'limit' => 0,
            ]
        );

        $expectedSearchParameters = (new OfferSearchParameters())
            ->withStart(new Natural(0))
            ->withLimit(new Natural(30));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->searchService->expects($this->once())
            ->method('search')
            ->with($expectedSearchParameters)
            ->willReturn($expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_works_with_a_min_age_of_zero_and_or_a_max_age_of_zero()
    {
        $request = new Request(
            [
                'start' => 0,
                'limit' => 0,
                'minAge' => 0,
                'maxAge' => 0,
            ]
        );

        $expectedSearchParameters = (new OfferSearchParameters())
            ->withStart(new Natural(0))
            ->withLimit(new Natural(30))
            ->withMinimumAge(new Natural(0))
            ->withMaximumAge(new Natural(0));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->searchService->expects($this->once())
            ->method('search')
            ->with($expectedSearchParameters)
            ->willReturn($expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @dataProvider embedParameterDataProvider
     * @test
     * @param mixed $embedParameter
     * @param bool $expectedEmbedParameter
     */
    public function it_converts_the_embed_parameter_to_correct_boolean_value(
        $embedParameter,
        $expectedEmbedParameter
    ) {
        $pagedCollectionFactory = $this->createMock(PagedCollectionFactory::class);

        $controller = new OfferSearchController(
            $this->searchService,
            $this->regionIndexName,
            $this->regionDocumentType,
            $this->queryStringFactory,
            $pagedCollectionFactory
        );

        $request = new Request(
            [
                'start' => 0,
                'limit' => 30,
                'embed' => $embedParameter,
            ]
        );

        $expectedSearchParameters = (new OfferSearchParameters())
            ->withStart(new Natural(0))
            ->withLimit(new Natural(30));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->searchService->expects($this->once())
            ->method('search')
            ->with($expectedSearchParameters)
            ->willReturn($expectedResultSet);

        $pagedCollectionFactory->expects($this->once())
            ->method('fromPagedResultSet')
            ->with(
                $expectedResultSet,
                0,
                30,
                $expectedEmbedParameter
            )
            ->willReturn($this->createMock(PagedCollection::class));

        $controller->search($request);
    }

    /**
     * @return Request[]
     */
    public function embedParameterDataProvider()
    {
        return [
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
                'true',
                true,
            ],
            [
                'TRUE',
                true,
            ],
            [
                '1',
                true
            ],
        ];
    }

    /**
     * @test
     */
    public function it_can_handle_a_single_string_value_for_parameters_that_are_normally_arrays()
    {
        $request = new Request(
            [
                'start' => 30,
                'limit' => 10,
                'labels' => 'foo',
                'organizerLabels' => 'bar',
                'locationLabels' => 'baz',
                'textLanguages' => 'nl',
            ]
        );

        $expectedSearchParameters = (new OfferSearchParameters())
            ->withStart(new Natural(30))
            ->withLimit(new Natural(10))
            ->withLabels(new LabelName('foo'))
            ->withOrganizerLabels(new LabelName('bar'))
            ->withLocationLabels(new LabelName('baz'))
            ->withTextLanguages(new Language('nl'));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->searchService->expects($this->once())
            ->method('search')
            ->with($expectedSearchParameters)
            ->willReturn($expectedResultSet);

        $this->controller->search($request);
    }
}
