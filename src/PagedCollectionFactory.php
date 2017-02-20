<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\JsonDocument\JsonDocumentTransformerInterface;
use CultuurNet\UDB3\Search\JsonDocument\PassThroughJsonDocumentTransformer;
use CultuurNet\UDB3\Search\PagedResultSet;

class PagedCollectionFactory implements PagedCollectionFactoryInterface
{
    /**
     * @var JsonDocumentTransformerInterface
     */
    private $embeddingJsonDocumentTransformer;

    /**
     * @param JsonDocumentTransformerInterface|null $embeddingJsonDocumentTransformer
     */
    public function __construct(JsonDocumentTransformerInterface $embeddingJsonDocumentTransformer = null)
    {
        if (is_null($embeddingJsonDocumentTransformer)) {
            $embeddingJsonDocumentTransformer = new PassThroughJsonDocumentTransformer();
        }

        $this->embeddingJsonDocumentTransformer = $embeddingJsonDocumentTransformer;
    }

    /**
     * @param PagedResultSet $pagedResultSet
     * @param int $start
     * @param int $limit
     * @param bool $embed
     * @return PagedCollection
     */
    public function fromPagedResultSet(
        PagedResultSet $pagedResultSet,
        $start,
        $limit,
        $embed = false
    ) {
        $results = array_map(
            function (JsonDocument $document) use ($embed) {
                if ($embed) {
                    $document = $this->embeddingJsonDocumentTransformer->transform($document);
                }

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
