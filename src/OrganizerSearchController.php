<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\Organizer\OrganizerSearchParameters;
use CultuurNet\UDB3\Search\Organizer\OrganizerSearchServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;
use ValueObjects\Web\Url;

class OrganizerSearchController
{
    /**
     * @var OrganizerSearchServiceInterface
     */
    private $searchService;

    /**
     * @var PagedCollectionFactoryInterface
     */
    private $pagedCollectionFactory;

    /**
     * @param OrganizerSearchServiceInterface $searchService
     * @param PagedCollectionFactoryInterface|null $pagedCollectionFactory
     */
    public function __construct(
        OrganizerSearchServiceInterface $searchService,
        PagedCollectionFactoryInterface $pagedCollectionFactory = null
    ) {
        if (is_null($pagedCollectionFactory)) {
            $pagedCollectionFactory = new ResultSetMappingPagedCollectionFactory();
        }

        $this->searchService = $searchService;
        $this->pagedCollectionFactory = $pagedCollectionFactory;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function search(Request $request)
    {
        $start = (int) $request->query->get('start', 0);
        $limit = (int) $request->query->get('limit', 30);

        // The embed option is returned as a string, and casting "false" to a
        // boolean returns true, so we have to do some extra conversion.
        $embedParameter = $request->query->get('embed', false);
        $embed = filter_var($embedParameter, FILTER_VALIDATE_BOOLEAN);

        if ($limit == 0) {
            $limit = 30;
        }

        $parameters = (new OrganizerSearchParameters())
            ->withStart(new Natural($start))
            ->withLimit(new Natural($limit));

        if (!empty($request->query->get('name'))) {
            $parameters = $parameters->withName(
                new StringLiteral($request->query->get('name'))
            );
        }

        if (!empty($request->query->get('website'))) {
            $parameters = $parameters->withWebsite(
                Url::fromNative($request->query->get('website'))
            );
        }

        $resultSet = $this->searchService->search($parameters);

        $pagedCollection = $this->pagedCollectionFactory->fromPagedResultSet(
            $resultSet,
            $start,
            $limit
        );

        return (new JsonResponse($pagedCollection, 200, ['Content-Type' => 'application/ld+json']))
            ->setPublic()
            ->setClientTtl(60 * 1)
            ->setTtl(60 * 5);
    }
}
