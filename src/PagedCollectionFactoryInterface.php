<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\Search\PagedResultSet;

interface PagedCollectionFactoryInterface
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
    );
}
