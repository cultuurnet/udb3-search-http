<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\JsonDocument\JsonDocumentTransformerInterface;
use CultuurNet\UDB3\Search\PagedResultSet;
use ValueObjects\Number\Natural;

class ResultTransformingPagedCollectionFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var JsonDocumentTransformerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $transformer;

    /**
     * @var ResultTransformingPagedCollectionFactory
     */
    private $factory;

    public function setUp()
    {
        $this->transformer = $this->createMock(JsonDocumentTransformerInterface::class);

        $this->factory = new ResultTransformingPagedCollectionFactory(
            $this->transformer
        );

        $this->transformer->expects($this->any())
            ->method('transform')
            ->willReturnCallback(
                function (JsonDocument $jsonDocument) {
                    $body = $jsonDocument->getBody();
                    $body->transformed = true;
                    return $jsonDocument->withBody($body);
                }
            );
    }

    /**
     * @test
     */
    public function it_creates_a_paged_collection_from_a_paged_result_set()
    {
        $start = 10;
        $limit = 10;
        $total = 12;

        $pagedResultSet = new PagedResultSet(
            new Natural($total),
            new Natural(10),
            [
                new JsonDocument(
                    '3d3ecf5c-2c21-4c6c-9faf-cd8e5fbf0464',
                    '{"@id": "events/3d3ecf5c-2c21-4c6c-9faf-cd8e5fbf0464"}'
                ),
                new JsonDocument(
                    'cd205d41-6534-4519-a38b-50937742d7ac',
                    '{"@id": "events/9f50a221-c6b3-486d-bede-603c75091dbe"}'
                ),
            ]
        );

        $expectedPageNumber = 2;

        $expectedCollection = new PagedCollection(
            $expectedPageNumber,
            $limit,
            [
                (object) [
                    '@id' => 'events/3d3ecf5c-2c21-4c6c-9faf-cd8e5fbf0464',
                    'transformed' => true,
                ],
                (object) [
                    '@id' => 'events/9f50a221-c6b3-486d-bede-603c75091dbe',
                    'transformed' => true,
                ],
            ],
            $total
        );

        $actualCollection = $this->factory->fromPagedResultSet($pagedResultSet, $start, $limit);

        $this->assertEquals($expectedCollection, $actualCollection);
    }
}
