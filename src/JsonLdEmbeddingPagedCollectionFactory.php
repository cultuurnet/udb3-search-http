<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\Search\PagedResultSet;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

class JsonLdEmbeddingPagedCollectionFactory implements PagedCollectionFactoryInterface
{
    /**
     * @var PagedCollectionFactoryInterface
     */
    private $pagedCollectionFactory;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @param PagedCollectionFactoryInterface $pagedCollectionFactory
     * @param ClientInterface $httpClient
     */
    public function __construct(
        PagedCollectionFactoryInterface $pagedCollectionFactory,
        ClientInterface $httpClient
    ) {
        $this->pagedCollectionFactory = $pagedCollectionFactory;
        $this->httpClient = $httpClient;
    }

    /**
     * @param PagedResultSet $pagedResultSet
     * @param int $start
     * @param int $limit
     * @return PagedCollection
     */
    public function fromPagedResultSet(
        PagedResultSet $pagedResultSet,
        $start,
        $limit
    ) {
        $pagedCollection = $this->pagedCollectionFactory->fromPagedResultSet($pagedResultSet, $start, $limit);
        $members = $pagedCollection->getMembers();

        $promises = $this->getJsonLdRequestPromises($members);

        $jsonLdBodies = [];

        $onResolved = function (array $responses) use (&$jsonLdBodies) {
            $jsonLdBodies = array_map(
                function (ResponseInterface $response) {
                    return json_decode($response->getBody());
                },
                $responses
            );
        };

        $onRejected = function (RequestException $e) {
            $url = (string) $e->getRequest()->getUri();
            $code = (string) $e->getResponse()->getStatusCode();
            throw new \RuntimeException("Could not embed document from url {$url}, received error code {$code}.");
        };

        \GuzzleHttp\Promise\all($promises)->then($onResolved, $onRejected)->wait();

        $merged = $this->mergeResults($members, $jsonLdBodies);

        return $pagedCollection->withMembers($merged);
    }

    /**
     * @param \stdClass[] $members
     * @return PromiseInterface[]
     */
    private function getJsonLdRequestPromises(array $members)
    {
        $promises = array_map(
            function (\stdClass $body) {
                if (isset($body->{'@id'})) {
                    return $this->httpClient->requestAsync('GET', $body->{'@id'});
                } else {
                    return null;
                }
            },
            $members
        );

        return array_filter($promises);
    }

    /**
     * @param array $originalResults
     * @param array $jsonLdResults
     * @return array
     */
    private function mergeResults(array $originalResults, array $jsonLdResults)
    {
        $mergedResults = $originalResults;

        foreach ($jsonLdResults as $jsonLd) {
            if (!isset($jsonLd->{'@id'})) {
                continue;
            }

            $mergedResults = array_map(
                function ($result) use ($jsonLd) {
                    if (isset($result->{'@id'}) && $result->{'@id'} == $jsonLd->{'@id'}) {
                        return $jsonLd;
                    } else {
                        return $result;
                    }
                },
                $mergedResults
            );
        }

        return $mergedResults;
    }
}
