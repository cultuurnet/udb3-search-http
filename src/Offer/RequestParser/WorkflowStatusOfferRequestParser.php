<?php

declare(strict_types=1);

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\UDB3\Search\Http\Parameters\SymfonyParameterBagAdapter;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use CultuurNet\UDB3\Search\Offer\WorkflowStatus;
use Symfony\Component\HttpFoundation\Request;

final class WorkflowStatusOfferRequestParser implements OfferRequestParserInterface
{
    private const PARAMETER = 'workflowStatus';
    private const DEFAULT = 'APPROVED,READY_FOR_VALIDATION';

    public function parse(Request $request, OfferQueryBuilderInterface $offerQueryBuilder)
    {
        $parameterBagReader = new SymfonyParameterBagAdapter($request->query);

        $workflowStatuses = $parameterBagReader->getExplodedStringFromParameter(
            self::PARAMETER,
            self::DEFAULT,
            function ($workflowStatus) {
                return new WorkflowStatus($workflowStatus);
            }
        );

        return $offerQueryBuilder->withWorkflowStatusFilter(...$workflowStatuses);
    }
}
