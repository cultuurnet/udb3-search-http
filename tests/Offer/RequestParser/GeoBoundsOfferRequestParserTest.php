<?php

declare(strict_types=1);

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\Geocoding\Coordinate\Coordinates;
use CultuurNet\Geocoding\Coordinate\Latitude;
use CultuurNet\Geocoding\Coordinate\Longitude;
use CultuurNet\UDB3\Search\GeoBoundsParameters;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class GeoBoundsOfferRequestParserTest extends TestCase
{
    /**
     * @var GeoBoundsOfferRequestParser
     */
    private $parser;

    /**
     * @var OfferQueryBuilderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $offerQueryBuilder;

    protected function setUp()
    {
        $this->parser = new GeoBoundsOfferRequestParser();
        $this->offerQueryBuilder = $this->createMock(OfferQueryBuilderInterface::class);
    }

    /**
     * @test
     */
    public function it_should_not_add_a_bounds_filter_if_no_bounds_parameter_is_given()
    {
        $request = new Request([]);

        $this->offerQueryBuilder->expects($this->never())
            ->method('withGeoBoundsFilter');

        $this->parser->parse($request, $this->offerQueryBuilder);
    }

    /**
     * @test
     */
    public function it_should_throw_an_exception_if_the_bounds_parameter_value_is_invalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $request = new Request(['bounds' => '34.172684,-118.604794,34.236144,-118.500938']);
        $this->parser->parse($request, $this->offerQueryBuilder);
    }

    /**
     * @test
     */
    public function it_should_add_a_bounds_filter_if_a_valid_bounds_parameter_is_given()
    {
        $request = new Request(
            [
                'bounds' => '34.172684,-118.604794|34.236144,-118.500938', // South-West | North-East
            ]
        );

        $this->offerQueryBuilder->expects($this->once())
            ->method('withGeoBoundsFilter')
            ->with(
                new GeoBoundsParameters(
                    // North-East
                    new Coordinates(
                        new Latitude(34.236144),
                        new Longitude(-118.500938)
                    ),
                    // South-West
                    new Coordinates(
                        new Latitude(34.172684),
                        new Longitude(-118.604794)
                    )
                )
            )
            ->willReturn($this->offerQueryBuilder);

        $this->parser->parse($request, $this->offerQueryBuilder);
    }
}
