<?php

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\Geocoding\Coordinate\Coordinates;
use CultuurNet\UDB3\Search\DistanceFactoryInterface;
use CultuurNet\UDB3\Search\GeoDistanceParameters;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use CultuurNet\UDB3\Search\SortOrder;
use Symfony\Component\HttpFoundation\Request;

class SortByOfferRequestParser implements OfferRequestParserInterface
{
    /**
     * @param Request $request
     * @param OfferQueryBuilderInterface $offerQueryBuilder
     * @return OfferQueryBuilderInterface
     */
    public function parse(Request $request, OfferQueryBuilderInterface $offerQueryBuilder)
    {
        $sorts = $request->query->get('sort', []);

        if (!is_array($sorts)) {
            throw new \InvalidArgumentException('Invalid sorting syntax given.');
        }

        $sortBuilders = [
            'score' => function (OfferQueryBuilderInterface $queryBuilder, SortOrder $sortOrder) {
                return $queryBuilder->withSortByScore($sortOrder);
            },
            'availableTo' => function (OfferQueryBuilderInterface $queryBuilder, SortOrder $sortOrder) {
                return $queryBuilder->withSortByAvailableTo($sortOrder);
            },
            'distance' => function (OfferQueryBuilderInterface $queryBuilder, SortOrder $sortOrder) use ($request) {
                $coordinates = $request->query->get('coordinates', false);
                if (!$coordinates) {
                    throw new \InvalidArgumentException(
                        'Required "coordinates" parameter missing when sorting by distance.'
                    );
                }

                $coordinates = Coordinates::fromLatLonString($coordinates);
                return $queryBuilder->withSortByDistance($coordinates, $sortOrder);
            },
            'created' => function (OfferQueryBuilderInterface $queryBuilder, SortOrder $sortOrder) {
                return $queryBuilder->withSortByCreated($sortOrder);
            },
            'modified' => function (OfferQueryBuilderInterface $queryBuilder, SortOrder $sortOrder) {
                return $queryBuilder->withSortByModified($sortOrder);
            },
        ];

        foreach ($sorts as $field => $order) {
            if (!isset($sortBuilders[$field])) {
                throw new \InvalidArgumentException("Invalid sort field '{$field}' given.");
            }

            try {
                $sortOrder = SortOrder::get($order);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException("Invalid sort order '{$order}' given.");
            }

            $callback = $sortBuilders[$field];
            $offerQueryBuilder = call_user_func($callback, $offerQueryBuilder, $sortOrder);
        }

        return $offerQueryBuilder;
    }
}
