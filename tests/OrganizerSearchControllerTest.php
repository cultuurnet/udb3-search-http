<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\Creator;
use CultuurNet\UDB3\Search\Http\Organizer\RequestParser\CompositeOrganizerRequestParser;
use CultuurNet\UDB3\Search\Http\Organizer\RequestParser\WorkflowStatusOrganizerRequestParser;
use CultuurNet\UDB3\Search\Organizer\OrganizerQueryBuilderInterface;
use CultuurNet\UDB3\Search\Organizer\OrganizerSearchServiceInterface;
use CultuurNet\UDB3\Search\Organizer\WorkflowStatus;
use CultuurNet\UDB3\Search\PagedResultSet;
use Symfony\Component\HttpFoundation\Request;
use ValueObjects\Geography\Country;
use ValueObjects\Geography\CountryCode;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;
use ValueObjects\Web\Domain;
use ValueObjects\Web\Url;

class OrganizerSearchControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MockOrganizerQueryBuilder
     */
    private $queryBuilder;

    /**
     * @var OrganizerSearchServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $searchService;

    /**
     * @var OrganizerSearchController
     */
    private $controller;

    /**
     * @var \CultuurNet\UDB3\Search\QueryStringFactoryInterface
     */
    private $queryStringFactory;

    public function setUp()
    {
        $this->queryBuilder = new MockOrganizerQueryBuilder();
        $this->searchService = $this->createMock(OrganizerSearchServiceInterface::class);
        $this->queryStringFactory = new MockQueryStringFactory();
        $this->controller = new OrganizerSearchController(
            $this->queryBuilder,
            $this->searchService,
            (new CompositeOrganizerRequestParser())
                ->withParser(new WorkflowStatusOrganizerRequestParser()),
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
                'q' => 'Foo bar',
                'textLanguages' => ['nl', 'en'],
                'name' => 'Foo',
                'website' => 'http://foo.bar',
                'postalCode' => 3000,
                'addressCountry' => 'NL',
                'creator' => 'Jan Janssens',
                'labels' => [
                    'Uitpas',
                    'foo',
                ],
                'workflowStatus' => 'ACTIVE,DELETED',
                'domain' => 'www.publiq.be'
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder
            ->withAutoCompleteFilter(new StringLiteral('Foo'))
            ->withAdvancedQuery(
                new MockQueryString('Foo bar'),
                new Language('nl'),
                new Language('en')
            )
            ->withWebsiteFilter(Url::fromNative('http://foo.bar'))
            ->withDomainFilter(Domain::specifyType('www.publiq.be'))
            ->withPostalCodeFilter(new PostalCode("3000"))
            ->withAddressCountryFilter(new Country(CountryCode::fromNative('NL')))
            ->withCreatorFilter(new Creator('Jan Janssens'))
            ->withLabelFilter(new LabelName('Uitpas'))
            ->withLabelFilter(new LabelName('foo'))
            ->withWorkflowStatusFilter(new WorkflowStatus('ACTIVE'), new WorkflowStatus('DELETED'))
            ->withStart(new Natural(30))
            ->withLimit(new Natural(10));

        $expectedResultSet = new PagedResultSet(
            new Natural(32),
            new Natural(10),
            [
                new JsonDocument('3f2ba18c-59a9-4f65-a242-462ad467c72b', '{"name": "Foo"}'),
                new JsonDocument('39d06346-b762-4ccd-8b3a-142a8f6abbbe', '{"name": "Foobar"}'),
            ]
        );

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $expectedJsonResponse = json_encode(
            [
                '@context' => 'http://www.w3.org/ns/hydra/context.jsonld',
                '@type' => 'PagedCollection',
                'itemsPerPage' => 10,
                'totalItems' => 32,
                'member' => [
                    ['name' => 'Foo'],
                    ['name' => 'Foobar'],
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

        $expectedQueryBuilder = $this->queryBuilder
            ->withStart(new Natural(0))
            ->withLimit(new Natural(30))
            ->withWorkflowStatusFilter(new WorkflowStatus('ACTIVE'));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

        $this->controller->search($request);
    }

    /**
     * @test
     */
    public function it_filters_out_deleted_organizers_by_default()
    {
        $request = new Request([]);

        $expectedQueryBuilder = $this->queryBuilder
            ->withStart(new Natural(0))
            ->withLimit(new Natural(30))
            ->withWorkflowStatusFilter(new WorkflowStatus('ACTIVE'));

        $expectedResultSet = new PagedResultSet(new Natural(30), new Natural(0), []);

        $this->expectQueryBuilderWillReturnResultSet($expectedQueryBuilder, $expectedResultSet);

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
                    'http://search.uitdatabank.be/organizers/',
                    'GET',
                    [
                        'frog' => [
                            'face',
                        ],
                    ]
                ),
                'expectedExceptionMessage' => 'Unknown query parameter(s): frog'
            ],
            'multiple unknown parameter' => [
                'request' => Request::create(
                    'http://search.uitdatabank.be/organizers/',
                    'GET',
                    [
                        'frog' => [
                            'face',
                        ],
                        'bat' => [
                            'cave',
                        ],
                    ]
                ),
                'expectedExceptionMessage' => 'Unknown query parameter(s): frog, bat'
            ],
            'unknown and whitelisted parameter' => [
                'request' => Request::create(
                    'http://search.uitdatabank.be/organizers/',
                    'GET',
                    [
                        'website' => [
                            'https://du.de',
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
     * @param OrganizerQueryBuilderInterface $expectedQueryBuilder
     * @param PagedResultSet $pagedResultSet
     */
    private function expectQueryBuilderWillReturnResultSet(
        OrganizerQueryBuilderInterface $expectedQueryBuilder,
        PagedResultSet $pagedResultSet
    ) {
        $this->searchService->expects($this->once())
            ->method('search')
            ->with(
                $this->callback(
                    function (OrganizerQueryBuilderInterface $actualQueryBuilder) use ($expectedQueryBuilder) {
                        $this->assertEquals(
                            $expectedQueryBuilder->build(),
                            $actualQueryBuilder->build()
                        );
                        return true;
                    }
                )
            )
            ->willReturn($pagedResultSet);
    }
}
