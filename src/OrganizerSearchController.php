<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Search\OrganizerSearchParameters;
use CultuurNet\UDB3\Search\OrganizerSearchServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueObjects\Number\Natural;
use ValueObjects\String\String as StringLiteral;

class OrganizerSearchController
{
    /**
     * @var OrganizerSearchServiceInterface
     */
    private $searchService;

    /**
     * @param OrganizerSearchServiceInterface $searchService
     */
    public function __construct(
        OrganizerSearchServiceInterface $searchService
    ) {
        $this->searchService = $searchService;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function search(Request $request)
    {
        $start = (int) $request->query->get('start', 0);
        $limit = (int) $request->query->get('limit', 30);

        if ($limit == 0) {
            $limit = 30;
        }

        $pageNumber = floor($start / $limit) + 1;

        $parameters = (new OrganizerSearchParameters())
            ->withStart(new Natural($start))
            ->withLimit(new Natural($limit));

        if (!empty($request->query->get('name'))) {
            $parameters = $parameters->withName(
                new StringLiteral($request->query->get('name'))
            );
        }

        $resultSet = $this->searchService->search($parameters);

        $results = array_map(
            function (JsonDocument $document) {
                return $document->getBody();
            },
            $resultSet->getResults()
        );

        $pagedCollection = new PagedCollection(
            $pageNumber,
            $limit,
            $results,
            $resultSet->getTotal()->toNative()
        );

        return (new JsonResponse($pagedCollection, 200, ['Content-Type' => 'application/ld+json']))
            ->setPublic()
            ->setClientTtl(60 * 1)
            ->setTtl(60 * 5);
    }
}
