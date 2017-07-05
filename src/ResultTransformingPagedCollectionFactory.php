<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\JsonDocument\JsonDocumentTransformerInterface;
use CultuurNet\UDB3\Search\PagedResultSet;

class ResultTransformingPagedCollectionFactory implements PagedCollectionFactoryInterface
{
    /**
     * @var JsonDocumentTransformerInterface
     */
    private $jsonDocumentTransformer;

    /**
     * @param JsonDocumentTransformerInterface $jsonDocumentTransformer
     */
    public function __construct(JsonDocumentTransformerInterface $jsonDocumentTransformer)
    {
        $this->jsonDocumentTransformer = $jsonDocumentTransformer;
    }

    /**
     * @param PagedResultSet $pagedResultSet
     * @param int $start
     * @param int $limit
     * @return PagedCollection
     */
    public function fromPagedResultSet(
        PagedResultSet $pagedResultSet,
        $start,
        $limit
    ) {
        $results = array_map(
            function (JsonDocument $document) {
                $document = $this->jsonDocumentTransformer->transform($document);
                return $document->getBody();
            },
            $pagedResultSet->getResults()
        );

        $pageNumber = (int) floor($start / $limit) + 1;

        return new PagedCollection(
            $pageNumber,
            $limit,
            $results,
            $pagedResultSet->getTotal()->toNative()
        );
    }
}
