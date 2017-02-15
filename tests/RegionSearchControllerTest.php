<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\Region\RegionId;
use CultuurNet\UDB3\Search\Region\RegionName;
use CultuurNet\UDB3\Search\Region\RegionNameMap;
use CultuurNet\UDB3\Search\Region\RegionSearchServiceInterface;
use Symfony\Component\HttpFoundation\Request;
use ValueObjects\StringLiteral\StringLiteral;

class RegionSearchControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RegionNameMap
     */
    private $regionNameMap;

    /**
     * @var RegionSearchServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $regionSearchService;

    /**
     * @var RegionSearchController
     */
    private $controller;

    public function setUp()
    {
        $this->regionNameMap = new RegionNameMap();
        $this->regionNameMap->register(new RegionId('11002'), new RegionName('Antwerpen'));
        $this->regionNameMap->register(new RegionId('24062'), new RegionName('Leuven'));

        $this->regionSearchService = $this->createMock(RegionSearchServiceInterface::class);

        $this->controller = new RegionSearchController(
            $this->regionSearchService,
            $this->regionNameMap
        );
    }

    /**
     * @test
     */
    public function it_returns_a_list_of_suggested_region_ids_and_names_for_a_given_input()
    {
        $request = new Request();
        $input = 'en';

        $mockedIds = [
            new RegionId('11002'), // Antwerpen.
            new RegionId('44021'), // Gent, not found in mapping.
            new RegionId('24062'), // Leuven.
        ];

        $expectedResponseData = [
            '11002' => 'Antwerpen',
            '24062' => 'Leuven',
        ];

        $this->regionSearchService->expects($this->once())
            ->method('suggest')
            ->with(new StringLiteral($input), null)
            ->willReturn($mockedIds);

        $response = $this->controller->suggest($request, $input);
        $actualResponseData = json_decode($response->getContent(), true);

        $this->assertEquals($expectedResponseData, $actualResponseData);
    }
}
