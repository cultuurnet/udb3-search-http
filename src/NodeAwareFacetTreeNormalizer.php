<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\Facet\FacetNode;
use CultuurNet\UDB3\Search\Facet\FacetTreeInterface;
use ValueObjects\StringLiteral\StringLiteral;

class NodeAwareFacetTreeNormalizer implements FacetTreeNormalizerInterface
{
    public function normalize(FacetTreeInterface $facetTree)
    {
        $data = [];

        if ($facetTree instanceof FacetNode) {
            $data['name'] = array_map(
                function (StringLiteral $translation) {
                    return $translation->toNative();
                },
                $facetTree->getName()->getTranslationsIncludingOriginal()
            );

            $data['count'] = $facetTree->getCount();

            $data['children'] = [];
            $normalizedChildren = &$data['children'];
        } else {
            $normalizedChildren = &$data;
        }

        foreach ($facetTree->getChildren() as $child) {
            $normalizedChildren[$child->getKey()] = $this->normalize($child);
        }

        if (isset($data['children']) && empty($data['children'])) {
            unset($data['children']);
        }

        return $data;
    }
}
