<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\Organizer\Organizer;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\Organizer\OrganizerSearchParameters;
use CultuurNet\UDB3\Search\Organizer\OrganizerSearchServiceInterface;
use CultuurNet\UDB3\Search\PagedResultSet;
use Symfony\Component\HttpFoundation\Request;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;
use ValueObjects\Web\Url;

class OrganizerSearchControllerTest extends \PHPUnit_Framework_TestCase
{
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
        $this->searchService = $this->createMock(OrganizerSearchServiceInterface::class);
        $this->controller = new OrganizerSearchController($this->searchService);
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
            ]
        );

        $expectedSearchParameters = (new OrganizerSearchParameters())
            ->withName(new StringLiteral('Foo'))
            ->withWebsite(Url::fromNative('http://foo.bar'))
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

        $expectedSearchParameters = (new OrganizerSearchParameters())
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
     * @dataProvider embedParameterDataProvider
     *
     * @param mixed $embedParameter
     * @param bool $expectedEmbedParameter
     */
    public function it_converts_the_embed_parameter_to_a_correct_boolean_and_passes_it_to_the_paged_collection_factory(
        $embedParameter,
        $expectedEmbedParameter
    ) {
        $pagedCollectionFactory = $this->createMock(PagedCollectionFactory::class);

        $controller = new OrganizerSearchController(
            $this->searchService,
            $pagedCollectionFactory
        );

        $request = new Request(
            [
                'start' => 0,
                'limit' => 30,
                'embed' => $embedParameter,
            ]
        );

        $expectedSearchParameters = (new OrganizerSearchParameters())
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
}
