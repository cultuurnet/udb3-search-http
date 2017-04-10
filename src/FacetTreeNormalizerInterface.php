<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\Facet\FacetTreeInterface;

interface FacetTreeNormalizerInterface
{
    /**
     * @param FacetTreeInterface $facetTree
     * @return array
     *   Array with exclusively scalar values.
     */
    public function normalize(FacetTreeInterface $facetTree);
}
