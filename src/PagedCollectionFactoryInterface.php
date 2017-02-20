<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\Search\PagedResultSet;

interface PagedCollectionFactoryInterface
{
    /**
     * @param PagedResultSet $pagedResultSet
     * @param int $pageNumber
     * @param int $limit
     * @param bool $embed
     * @return PagedCollection
     */
    public function fromPagedResultSet(
        PagedResultSet $pagedResultSet,
        $pageNumber,
        $limit,
        $embed = false
    );
}
