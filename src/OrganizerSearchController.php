<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Search\Creator;
use CultuurNet\UDB3\Search\Http\Parameters\OrganizerParameterWhiteList;
use CultuurNet\UDB3\Search\JsonDocument\PassThroughJsonDocumentTransformer;
use CultuurNet\UDB3\Search\Organizer\OrganizerQueryBuilderInterface;
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
     * @var OrganizerQueryBuilderInterface
     */
    private $queryBuilder;

    /**
     * @var OrganizerSearchServiceInterface
     */
    private $searchService;

    /**
     * @var PagedCollectionFactoryInterface
     */
    private $pagedCollectionFactory;

    /**
     * @var OrganizerParameterWhiteList
     */
    private $organizerParameterWhiteList;

    /**
     * @param OrganizerQueryBuilderInterface $queryBuilder
     * @param OrganizerSearchServiceInterface $searchService
     * @param PagedCollectionFactoryInterface|null $pagedCollectionFactory
     */
    public function __construct(
        OrganizerQueryBuilderInterface $queryBuilder,
        OrganizerSearchServiceInterface $searchService,
        PagedCollectionFactoryInterface $pagedCollectionFactory = null
    ) {
        if (is_null($pagedCollectionFactory)) {
            $pagedCollectionFactory = new ResultTransformingPagedCollectionFactory(
                new PassThroughJsonDocumentTransformer()
            );
        }

        $this->queryBuilder = $queryBuilder;
        $this->searchService = $searchService;
        $this->pagedCollectionFactory = $pagedCollectionFactory;
        $this->organizerParameterWhiteList = new OrganizerParameterWhiteList();
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function search(Request $request)
    {
        $this->organizerParameterWhiteList->validateParameters(
            $request->query->keys()
        );

        $start = (int) $request->query->get('start', 0);
        $limit = (int) $request->query->get('limit', 30);

        if ($limit == 0) {
            $limit = 30;
        }

        $queryBuilder = $this->queryBuilder
            ->withStart(new Natural($start))
            ->withLimit(new Natural($limit));

        if (!empty($request->query->get('name'))) {
            $queryBuilder = $queryBuilder->withAutoCompleteFilter(
                new StringLiteral($request->query->get('name'))
            );
        }

        if (!empty($request->query->get('website'))) {
            $queryBuilder = $queryBuilder->withWebsiteFilter(
                Url::fromNative($request->query->get('website'))
            );
        }

        $postalCode = (string) $request->query->get('postalCode');
        if (!empty($postalCode)) {
            $queryBuilder = $queryBuilder->withPostalCodeFilter(
                new PostalCode($postalCode)
            );
        }

        if ($request->query->get('creator')) {
            $queryBuilder = $queryBuilder->withCreatorFilter(
                new Creator($request->query->get('creator'))
            );
        }

        $resultSet = $this->searchService->search($queryBuilder);

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
