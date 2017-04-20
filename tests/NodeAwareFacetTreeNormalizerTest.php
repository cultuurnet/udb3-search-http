<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Search\Facet\FacetFilter;
use CultuurNet\UDB3\Search\Facet\FacetNode;
use CultuurNet\UDB3\ValueObject\MultilingualString;
use ValueObjects\StringLiteral\StringLiteral;

class NodeAwareFacetTreeNormalizerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var NodeAwareFacetTreeNormalizer
     */
    private $normalizer;

    public function setUp()
    {
        $this->normalizer = new NodeAwareFacetTreeNormalizer();
    }

    /**
     * @test
     */
    public function it_normalizes_a_facet_tree_to_an_associative_array_and_includes_node_specific_details()
    {
        $expectedJson = file_get_contents(__DIR__ . '/data/facets.json');
        $expectedArray = json_decode($expectedJson, true);

        $facets = [
            new FacetFilter(
                'region',
                [
                    new FacetNode(
                        'prv-vlaams-brabant',
                        new MultilingualString(
                            new Language('nl'),
                            new StringLiteral('Vlaams-Brabant')
                        ),
                        20,
                        [
                            new FacetNode(
                                'gem-leuven',
                                new MultilingualString(
                                    new Language('nl'),
                                    new StringLiteral('Leuven')
                                ),
                                15
                            ),
                            new FacetNode(
                                'gem-diest',
                                new MultilingualString(
                                    new Language('nl'),
                                    new StringLiteral('Diest')
                                ),
                                5
                            ),
                        ]
                    ),
                    new FacetNode(
                        'prv-antwerpen',
                        new MultilingualString(
                            new Language('nl'),
                            new StringLiteral('Antwerpen')
                        ),
                        32,
                        [
                            new FacetNode(
                                'gem-antwerpen',
                                new MultilingualString(
                                    new Language('nl'),
                                    new StringLiteral('Antwerpen')
                                ),
                                17
                            ),
                            new FacetNode(
                                'gem-westerlo',
                                new MultilingualString(
                                    new Language('nl'),
                                    new StringLiteral('Westerlo')
                                ),
                                15
                            ),
                        ]
                    ),
                ]
            ),
            new FacetFilter(
                'term',
                [
                    new FacetNode(
                        '0.11.6.5',
                        new MultilingualString(
                            new Language('nl'),
                            new StringLiteral('Jeugdhuis of jeugdcentrum')
                        ),
                        7
                    ),
                    new FacetNode(
                        '0.11.6.7',
                        new MultilingualString(
                            new Language('nl'),
                            new StringLiteral('Bibliotheek')
                        ),
                        14
                    ),
                ]
            ),
        ];

        $actualArray = [];
        foreach ($facets as $facet) {
            $actualArray[$facet->getKey()] = $this->normalizer->normalize($facet);
        }

        $this->assertEquals($expectedArray, $actualArray);
    }
}
