<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Search\Offer\OfferSearchParameters;
use CultuurNet\UDB3\Search\Offer\OfferSearchServiceInterface;
use CultuurNet\UDB3\Search\QueryStringFactoryInterface;
use CultuurNet\UDB3\Search\Region\RegionId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;

class OfferSearchController
{
    /**
     * @var OfferSearchServiceInterface
     */
    private $searchService;

    /**
     * @var StringLiteral
     */
    private $regionIndexName;

    /**
     * @var StringLiteral
     */
    private $regionDocumentType;

    /**
     * @var PagedCollectionFactoryInterface
     */
    private $pagedCollectionFactory;

    /**
     * @param OfferSearchServiceInterface $searchService
     * @param StringLiteral $regionIndexName
     * @param StringLiteral $regionDocumentType
     * @param QueryStringFactoryInterface $queryStringFactory
     * @param PagedCollectionFactoryInterface|null $pagedCollectionFactory
     */
    public function __construct(
        OfferSearchServiceInterface $searchService,
        StringLiteral $regionIndexName,
        StringLiteral $regionDocumentType,
        QueryStringFactoryInterface $queryStringFactory,
        PagedCollectionFactoryInterface $pagedCollectionFactory = null
    ) {
        if (is_null($pagedCollectionFactory)) {
            $pagedCollectionFactory = new PagedCollectionFactory();
        }

        $this->searchService = $searchService;
        $this->regionIndexName = $regionIndexName;
        $this->regionDocumentType = $regionDocumentType;
        $this->queryStringFactory = $queryStringFactory;
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
        $embed = (bool) $request->query->get('embed', false);

        if ($limit == 0) {
            $limit = 30;
        }

        $parameters = (new OfferSearchParameters())
            ->withStart(new Natural($start))
            ->withLimit(new Natural($limit));

        if (!empty($request->query->get('q'))) {
            $parameters = $parameters->withQueryString(
                $this->queryStringFactory->fromString(
                    $request->query->get('q')
                )
            );
        }

        if (!empty($request->query->get('textLanguages'))) {
            $parameters = $parameters->withTextLanguages(
                ...array_map(
                    function ($language) {
                        return new Language($language);
                    },
                    (array) $request->query->get('textLanguages')
                )
            );
        }

        if (!empty($request->query->get('regionId'))) {
            $parameters = $parameters->withRegion(
                new RegionId($request->query->get('regionId')),
                $this->regionIndexName,
                $this->regionDocumentType
            );
        }

        $labels = $this->getLabelsFromQuery($request, 'labels');
        if (!empty($labels)) {
            $parameters = $parameters->withLabels(...$labels);
        }

        $locationLabels = $this->getLabelsFromQuery($request, 'locationLabels');
        if (!empty($locationLabels)) {
            $parameters = $parameters->withLocationLabels(...$locationLabels);
        }

        $organizerLabels = $this->getLabelsFromQuery($request, 'organizerLabels');
        if (!empty($organizerLabels)) {
            $parameters = $parameters->withOrganizerLabels(...$organizerLabels);
        }

        $resultSet = $this->searchService->search($parameters);

        $pagedCollection = $this->pagedCollectionFactory->fromPagedResultSet(
            $resultSet,
            $start,
            $limit,
            $embed
        );

        return (new JsonResponse($pagedCollection, 200, ['Content-Type' => 'application/ld+json']))
            ->setPublic()
            ->setClientTtl(60 * 1)
            ->setTtl(60 * 5);
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return LabelName[]
     */
    private function getLabelsFromQuery(Request $request, $queryParameter)
    {
        if (empty($request->query->get($queryParameter))) {
            return [];
        }

        $labels = (array) $request->query->get($queryParameter);

        return array_map(
            function ($label) {
                return new LabelName($label);
            },
            $labels
        );
    }
}
