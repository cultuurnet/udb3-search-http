<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\JsonDocument\JsonDocumentTransformerInterface;
use CultuurNet\UDB3\Search\JsonDocument\PassThroughJsonDocumentTransformer;
use CultuurNet\UDB3\Search\PagedResultSet;

class ResultSetMappingPagedCollectionFactory implements PagedCollectionFactoryInterface
{
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
