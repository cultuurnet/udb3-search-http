<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Geocoding\Coordinate\Coordinates;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\Search\DistanceFactoryInterface;
use CultuurNet\UDB3\Search\GeoDistanceParameters;
use CultuurNet\UDB3\Search\Offer\AudienceType;
use CultuurNet\UDB3\Search\Offer\OfferSearchParameters;
use CultuurNet\UDB3\Search\Offer\OfferSearchServiceInterface;
use CultuurNet\UDB3\Search\Offer\WorkflowStatus;
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
     * @var QueryStringFactoryInterface
     */
    private $queryStringFactory;

    /**
     * @var DistanceFactoryInterface
     */
    private $distanceFactory;

    /**
     * @var PagedCollectionFactoryInterface
     */
    private $pagedCollectionFactory;

    /**
     * @param OfferSearchServiceInterface $searchService
     * @param StringLiteral $regionIndexName
     * @param StringLiteral $regionDocumentType
     * @param QueryStringFactoryInterface $queryStringFactory
     * @param DistanceFactoryInterface $distanceFactory
     * @param PagedCollectionFactoryInterface|null $pagedCollectionFactory
     */
    public function __construct(
        OfferSearchServiceInterface $searchService,
        StringLiteral $regionIndexName,
        StringLiteral $regionDocumentType,
        QueryStringFactoryInterface $queryStringFactory,
        DistanceFactoryInterface $distanceFactory,
        PagedCollectionFactoryInterface $pagedCollectionFactory = null
    ) {
        if (is_null($pagedCollectionFactory)) {
            $pagedCollectionFactory = new PagedCollectionFactory();
        }

        $this->searchService = $searchService;
        $this->regionIndexName = $regionIndexName;
        $this->regionDocumentType = $regionDocumentType;
        $this->queryStringFactory = $queryStringFactory;
        $this->distanceFactory = $distanceFactory;
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

        $textLanguages = $this->getLanguagesFromQuery($request, 'textLanguages');
        if (!empty($textLanguages)) {
            $parameters = $parameters->withTextLanguages(...$textLanguages);
        }

        $languages = $this->getLanguagesFromQuery($request, 'languages');
        if (!empty($languages)) {
            $parameters = $parameters->withLanguages(...$languages);
        }

        if (!empty($request->query->get('workflowStatus'))) {
            $parameters = $parameters->withWorkflowStatus(
                new WorkflowStatus($request->query->get('workflowStatus'))
            );
        }

        if (!empty($request->query->get('regionId'))) {
            $parameters = $parameters->withRegion(
                new RegionId($request->query->get('regionId')),
                $this->regionIndexName,
                $this->regionDocumentType
            );
        }

        $coordinates = $request->query->get('coordinates', false);
        $distance = $request->query->get('distance', false);

        if ($coordinates && !$distance) {
            throw new \InvalidArgumentException('Required "distance" parameter missing when searching by coordinates.');
        } elseif ($distance && !$coordinates) {
            throw new \InvalidArgumentException('Required "coordinates" parameter missing when searching by distance.');
        } elseif ($coordinates && $distance) {
            $parameters = $parameters->withGeoDistanceParameters(
                new GeoDistanceParameters(
                    Coordinates::fromLatLonString($coordinates),
                    $this->distanceFactory->fromString($distance)
                )
            );
        }


        // Do strict comparison to make sure 0 gets included.
        if ($request->query->get('minAge', false) !== false) {
            $parameters = $parameters->withMinimumAge(
                new Natural($request->query->get('minAge'))
            );
        }

        // Do strict comparison to make sure 0 gets included.
        if ($request->query->get('maxAge', false) !== false) {
            $parameters = $parameters->withMaximumAge(
                new Natural($request->query->get('maxAge'))
            );
        }

        // Do strict comparison to make sure 0 gets included.
        if ($request->query->get('price', false) !== false) {
            $parameters = $parameters->withPrice(
                Price::fromFloat((float) $request->query->get('price'))
            );
        }

        // Do strict comparison to make sure 0 gets included.
        if ($request->query->get('minPrice', false) !== false) {
            $parameters = $parameters->withMinimumPrice(
                Price::fromFloat((float) $request->query->get('minPrice'))
            );
        }

        // Do strict comparison to make sure 0 gets included.
        if ($request->query->get('maxPrice', false) !== false) {
            $parameters = $parameters->withMaximumPrice(
                Price::fromFloat((float) $request->query->get('maxPrice'))
            );
        }

        if ($request->query->get('audienceType')) {
            $parameters = $parameters->withAudienceType(
                new AudienceType($request->query->get('audienceType'))
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
        return $this->getArrayFromQueryParameters(
            $request,
            $queryParameter,
            function ($value) {
                return new LabelName($value);
            }
        );
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @return Language[]
     */
    private function getLanguagesFromQuery(Request $request, $queryParameter)
    {
        return $this->getArrayFromQueryParameters(
            $request,
            $queryParameter,
            function ($value) {
                return new Language($value);
            }
        );
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @param callable|null $callback
     * @return array
     */
    private function getArrayFromQueryParameters(Request $request, $queryParameter, callable $callback = null)
    {
        if (empty($request->query->get($queryParameter))) {
            return [];
        }

        $values = (array) $request->query->get($queryParameter);

        if (!is_null($callback)) {
            $values = array_map($callback, $values);
        }

        return $values;
    }
}
