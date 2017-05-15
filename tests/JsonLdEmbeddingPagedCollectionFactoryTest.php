<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\PagedResultSet;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use ValueObjects\Number\Natural;

class JsonLdEmbeddingPagedCollectionFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var JsonDocument[]
     */
    private $jsonDocuments;

    /**
     * @var int
     */
    private $start;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var int
     */
    private $total;

    /**
     * @var PagedResultSet
     */
    private $pagedResultSet;

    /**
     * @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    public function setUp()
    {
        $documentIds = [
            'fe775848-7552-4450-8479-419f47ad0a9f',
            '4c8b6b38-d94d-495a-b4fe-6ae2f701a935',
            'feaae8dc-6eaa-4076-ab3f-5bbbb735494b',
            'f127909c-9e2b-46b6-a4aa-0c19270437f8',
        ];

        $this->jsonDocuments = array_map(
            function ($id) {
                return new JsonDocument($id, json_encode(['@id' => "http://mock/event/{$id}"]));
            },
            $documentIds
        );

        $jsonDocumentWithoutUrl = new JsonDocument(
            '6da8c68a-6b19-4692-abfa-092fde6eb7da',
            json_encode(['has_no_@id' => 'should still be in the final paged collection'])
        );

        // Put the document without @id in the middle of the jsonDocuments.
        array_splice($this->jsonDocuments, 2, 0, [$jsonDocumentWithoutUrl]);

        $this->start = 0;
        $this->limit = 5;
        $this->total = 20;

        $this->pagedResultSet = new PagedResultSet(
            new Natural($this->total),
            new Natural($this->limit),
            $this->jsonDocuments
        );

        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * @test
     */
    public function it_embeds_all_documents_that_have_an_id_url_in_both_the_original_and_returned_document()
    {
        // Guzzle should return the responses in the correct order, but let's
        // test with an incorrect order anyway. Also make one response not
        // contain an @id.
        $responseDocumentIds = [
            'f127909c-9e2b-46b6-a4aa-0c19270437f8',
            '4c8b6b38-d94d-495a-b4fe-6ae2f701a935',
            null,
            'feaae8dc-6eaa-4076-ab3f-5bbbb735494b',
        ];

        $expectedResponses = array_map(
            function ($id) {
                $json = new \stdClass();

                if ($id) {
                    $json->{'@id'} = "http://mock/event/{$id}";
                }

                // Random data that could be returned as part of the complete
                // json-ld document.
                $json->foo = 'bar';

                return new Response(200, [], json_encode($json));
            },
            $responseDocumentIds
        );

        $expectedPagedCollection = new PagedCollection(
            1,
            $this->limit,
            [
                // Response did not contain an @id.
                (object) [
                    '@id' => 'http://mock/event/fe775848-7552-4450-8479-419f47ad0a9f',
                ],
                // Valid response.
                (object) [
                    '@id' => 'http://mock/event/4c8b6b38-d94d-495a-b4fe-6ae2f701a935',
                    'foo' => 'bar',
                ],
                // Original document did not contain an @id.
                (object) [
                    'has_no_@id' => 'should still be in the final paged collection'
                ],
                // Valid response.
                (object) [
                    '@id' => 'http://mock/event/feaae8dc-6eaa-4076-ab3f-5bbbb735494b',
                    'foo' => 'bar',
                ],
                // Valid response.
                (object) [
                    '@id' => 'http://mock/event/f127909c-9e2b-46b6-a4aa-0c19270437f8',
                    'foo' => 'bar',
                ],
            ],
            $this->total
        );

        $mockClient = $this->createMockClient(new MockHandler($expectedResponses));

        $factory = new JsonLdEmbeddingPagedCollectionFactory(
            new ResultSetMappingPagedCollectionFactory(),
            $mockClient,
            $this->logger
        );

        $this->logger->expects($this->never())
            ->method('error');

        $actualPagedCollection = $factory->fromPagedResultSet(
            $this->pagedResultSet,
            $this->start,
            $this->limit
        );

        $this->assertEquals($expectedPagedCollection, $actualPagedCollection);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_at_least_one_request_failed()
    {
        $expectedResponses = [
            new Response(200, [], json_encode('{"@id":"http://mock/event/fe775848-7552-4450-8479-419f47ad0a9f"}')),
            new Response(200, [], json_encode('{"@id":"http://mock/event/4c8b6b38-d94d-495a-b4fe-6ae2f701a935"}')),
            new Response(404),
            new Response(200, [], json_encode('{"@id":"http://mock/event/f127909c-9e2b-46b6-a4aa-0c19270437f8"}')),
        ];

        $mockClient = $this->createMockClient(new MockHandler($expectedResponses));

        $failedUrl = "http://mock/event/feaae8dc-6eaa-4076-ab3f-5bbbb735494b";

        $this->logger->expects($this->once())
            ->method('error')
            ->with("Could not embed document from url {$failedUrl}, received error code 404.");

        $factory = new JsonLdEmbeddingPagedCollectionFactory(
            new ResultSetMappingPagedCollectionFactory(),
            $mockClient,
            $this->logger
        );

        $factory->fromPagedResultSet(
            $this->pagedResultSet,
            $this->start,
            $this->limit
        );
    }

    /**
     * @param MockHandler $mockHandler
     * @return Client
     */
    private function createMockClient(MockHandler $mockHandler)
    {
        $handler = HandlerStack::create($mockHandler);
        return new Client(['handler' => $handler]);
    }
}
