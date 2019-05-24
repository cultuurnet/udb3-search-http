<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Search\Creator;
use CultuurNet\UDB3\Search\Http\Organizer\RequestParser\OrganizerRequestParser;
use CultuurNet\UDB3\Search\Http\Parameters\OrganizerParameterWhiteList;
use CultuurNet\UDB3\Search\Http\Parameters\ParameterBagInterface;
use CultuurNet\UDB3\Search\Http\Parameters\SymfonyParameterBagAdapter;
use CultuurNet\UDB3\Search\JsonDocument\PassThroughJsonDocumentTransformer;
use CultuurNet\UDB3\Search\Organizer\OrganizerQueryBuilderInterface;
use CultuurNet\UDB3\Search\Organizer\OrganizerSearchServiceInterface;
use CultuurNet\UDB3\Search\QueryStringFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;
use ValueObjects\Web\Domain;
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
     * @var QueryStringFactoryInterface
     */
    private $queryStringFactory;

    /**
     * @var OrganizerRequestParser
     */
    private $organizerRequestParser;

    /**
     * @param OrganizerQueryBuilderInterface $queryBuilder
     * @param OrganizerSearchServiceInterface $searchService
     * @param OrganizerRequestParser $organizerRequestParser
     * @param QueryStringFactoryInterface $queryStringFactory
     * @param PagedCollectionFactoryInterface|null $pagedCollectionFactory
     */
    public function __construct(
        OrganizerQueryBuilderInterface $queryBuilder,
        OrganizerSearchServiceInterface $searchService,
        OrganizerRequestParser $organizerRequestParser,
        QueryStringFactoryInterface $queryStringFactory,
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
        $this->queryStringFactory = $queryStringFactory;
        $this->organizerRequestParser = $organizerRequestParser;
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

        $parameterBag = new SymfonyParameterBagAdapter($request->query);

        $queryBuilder = $this->queryBuilder
            ->withStart(new Natural($start))
            ->withLimit(new Natural($limit));

        $queryBuilder = $this->organizerRequestParser->parse($request, $queryBuilder);

        $textLanguages = $this->getLanguagesFromQuery($parameterBag, 'textLanguages');

        if (!empty($request->query->get('q'))) {
            $queryBuilder = $queryBuilder->withAdvancedQuery(
                $this->queryStringFactory->fromString(
                    $request->query->get('q')
                ),
                ...$textLanguages
            );
        }

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

        if (!empty($request->query->get('domain'))) {
            $queryBuilder = $queryBuilder->withDomainFilter(
                Domain::specifyType($request->query->get('domain'))
            );
        }

        $postalCode = (string) $request->query->get('postalCode');
        if (!empty($postalCode)) {
            $queryBuilder = $queryBuilder->withPostalCodeFilter(
                new PostalCode($postalCode)
            );
        }

        $country = (new CountryExtractor())->getCountryFromQuery(
            $parameterBag,
            null
        );
        if (!empty($country)) {
            $queryBuilder = $queryBuilder->withAddressCountryFilter($country);
        }

        if ($request->query->get('creator')) {
            $queryBuilder = $queryBuilder->withCreatorFilter(
                new Creator($request->query->get('creator'))
            );
        }

        $labels = $this->getLabelsFromQuery($parameterBag, 'labels');
        foreach ($labels as $label) {
            $queryBuilder = $queryBuilder->withLabelFilter($label);
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

    /**
     * @param ParameterBagInterface $parameterBag
     * @param string $queryParameter
     * @return LabelName[]
     */
    private function getLabelsFromQuery(ParameterBagInterface $parameterBag, $queryParameter)
    {
        return $parameterBag->getArrayFromParameter(
            $queryParameter,
            function ($value) {
                return new LabelName($value);
            }
        );
    }

    /**
     * @param ParameterBagInterface $parameterBag
     * @param string $queryParameter
     * @return Language[]
     */
    private function getLanguagesFromQuery(ParameterBagInterface $parameterBag, $queryParameter)
    {
        return $parameterBag->getArrayFromParameter(
            $queryParameter,
            function ($value) {
                return new Language($value);
            }
        );
    }
}
