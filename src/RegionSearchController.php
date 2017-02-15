<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\Region\RegionId;
use CultuurNet\UDB3\Search\Region\RegionNameMap;
use CultuurNet\UDB3\Search\Region\RegionSearchServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;

class RegionSearchController
{
    /**
     * @var RegionSearchServiceInterface
     */
    private $regionSearchService;

    /**
     * @var RegionNameMap
     */
    private $regionNameMap;

    /**
     * @param RegionSearchServiceInterface $regionSearchService
     * @param RegionNameMap $regionNameMap
     */
    public function __construct(
        RegionSearchServiceInterface $regionSearchService,
        RegionNameMap $regionNameMap
    ) {
        $this->regionSearchService = $regionSearchService;
        $this->regionNameMap = $regionNameMap;
    }

    /**
     * @param Request $request
     * @param string $input
     * @return Response
     */
    public function suggest(Request $request, $input)
    {
        $input = new StringLiteral($input);

        $limit = (int) $request->query->get('limit', 0);
        $limit = $limit > 0 ? new Natural($limit) : null;

        $regionIds = $this->regionSearchService->suggest($input, $limit);
        $regionNames = [];

        foreach ($regionIds as $regionId) {
            $regionName = $this->regionNameMap->find($regionId);

            if (!is_null($regionName)) {
                $regionNames[$regionId->toNative()] = $regionName->toNative();
            }
        }

        return new JsonResponse($regionNames);
    }
}
