<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\ElasticSearch\Organizer\ElasticSearchOrganizerQueryBuilder;
use CultuurNet\UDB3\Search\Organizer\OrganizerQueryBuilderInterface;
use CultuurNet\UDB3\Search\Organizer\OrganizerSearchServiceInterface;
use CultuurNet\UDB3\Search\PagedResultSet;
use Symfony\Component\HttpFoundation\Request;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;
use ValueObjects\Web\Url;

class OrganizerSearchControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ElasticSearchOrganizerQueryBuilder
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

    public function setUp()
    {
        $this->queryBuilder = new ElasticSearchOrganizerQueryBuilder();
        $this->searchService = $this->createMock(OrganizerSearchServiceInterface::class);
        $this->controller = new OrganizerSearchController($this->queryBuilder, $this->searchService);
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
                'name' => 'Foo',
                'website' => 'http://foo.bar',
                'postalCode' => 3000,
            ]
        );

        $expectedQueryBuilder = $this->queryBuilder
            ->withAutoCompleteFilter(new StringLiteral('Foo'))
            ->withWebsiteFilter(Url::fromNative('http://foo.bar'))
            ->withPostalCodeFilter(new PostalCode("3000"))
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
            ->withLimit(new Natural(30));

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
