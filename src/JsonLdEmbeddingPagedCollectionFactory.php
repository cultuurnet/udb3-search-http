<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Hydra\PagedCollection;
use CultuurNet\UDB3\Search\PagedResultSet;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PagedCollectionFactoryInterface $pagedCollectionFactory
     * @param ClientInterface $httpClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        PagedCollectionFactoryInterface $pagedCollectionFactory,
        ClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->pagedCollectionFactory = $pagedCollectionFactory;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
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

        $results = \GuzzleHttp\Promise\settle($promises)->wait();

        foreach ($results as $key => $result) {
            switch ($result['state']) {
                case Promise::FULFILLED:
                    /** @var ResponseInterface $response */
                    $response = $result['value'];
                    $jsonLdBodies[] = json_decode($response->getBody());
                    break;

                default:
                    $this->logClientException($result['reason']);
                    break;
            }
        }

        $merged = $this->mergeResults($members, $jsonLdBodies);

        return $pagedCollection->withMembers($merged);
    }

    /**
     * @param \stdClass[] $members
     * @return PromiseInterface[]
     */
    private function getJsonLdRequestPromises(array $members)
    {
        $promises = [];

        foreach ($members as $body) {
            if (isset($body->{'@id'})) {
                $promises[$body->{'@id'}] = $this->httpClient->requestAsync(
                    'GET',
                    $body->{'@id'}
                );
            }
        }

        return $promises;
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

    /**
     * @param ClientException $exception
     */
    private function logClientException(ClientException $exception)
    {
        $url = (string) $exception->getRequest()->getUri();
        $code = (string) $exception->getResponse()->getStatusCode();
        $message = "Could not embed document from url {$url}, received error code {$code}.";

        $this->logger->error($message);
    }
}
