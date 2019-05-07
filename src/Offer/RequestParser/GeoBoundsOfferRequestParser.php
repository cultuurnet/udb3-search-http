<?php

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\Geocoding\Coordinate\Coordinates;
use CultuurNet\Geocoding\Coordinate\Latitude;
use CultuurNet\Geocoding\Coordinate\Longitude;
use CultuurNet\UDB3\Search\DistanceFactoryInterface;
use CultuurNet\UDB3\Search\GeoBoundsParameters;
use CultuurNet\UDB3\Search\GeoDistanceParameters;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

class GeoBoundsOfferRequestParser implements OfferRequestParserInterface
{
    private const BOUNDS_REGEX = '([0-9\.-]+),([0-9\.-]+)\|([0-9\.-]+),([0-9\.-]+)';

    /**
     * @param Request $request
     * @param OfferQueryBuilderInterface $offerQueryBuilder
     * @return OfferQueryBuilderInterface
     */
    public function parse(Request $request, OfferQueryBuilderInterface $offerQueryBuilder)
    {
        $bounds = $request->query->get('bounds', false);
        if (!$bounds) {
            return $offerQueryBuilder;
        }

        $matches = [];
        if (!preg_match('/' . self::BOUNDS_REGEX . '/', $bounds, $matches)) {
            throw new \InvalidArgumentException(
                'Bounds parameter should be in the "southWestLat,southWestLong|northEastLat,NorthEastLong" format.'
            );
        }

        $southWestLat = (float) $matches[1];
        $southWestLong = (float) $matches[2];
        $northEastLat = (float) $matches[3];
        $northEastLong = (float) $matches[4];

        $southWest = new Coordinates(
            new Latitude($southWestLat),
            new Longitude($southWestLong)
        );

        $northEast = new Coordinates(
            new Latitude($northEastLat),
            new Longitude($northEastLong)
        );

        $offerQueryBuilder = $offerQueryBuilder->withGeoBoundsFilter(
            new GeoBoundsParameters($northEast, $southWest)
        );

        return $offerQueryBuilder;
    }
}
