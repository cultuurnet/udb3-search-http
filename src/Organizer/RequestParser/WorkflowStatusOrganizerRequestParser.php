<?php

declare(strict_types=1);

namespace CultuurNet\UDB3\Search\Http\Organizer\RequestParser;

use CultuurNet\UDB3\Search\Http\Parameters\SymfonyParameterBagAdapter;
use CultuurNet\UDB3\Search\Organizer\OrganizerQueryBuilderInterface;
use CultuurNet\UDB3\Search\Organizer\WorkflowStatus;
use Symfony\Component\HttpFoundation\Request;

final class WorkflowStatusOrganizerRequestParser implements OrganizerRequestParser
{
    private const PARAMETER = 'workflowStatus';
    private const DEFAULT = 'ACTIVE';

    public function parse(
        Request $request,
        OrganizerQueryBuilderInterface $organizerQueryBuilder
    ): OrganizerQueryBuilderInterface {
        $parameterBagReader = new SymfonyParameterBagAdapter($request->query);

        $workflowStatuses = $parameterBagReader->getExplodedStringFromParameter(
            self::PARAMETER,
            self::DEFAULT,
            function ($workflowStatus) {
                return new WorkflowStatus($workflowStatus);
            }
        );

        return $organizerQueryBuilder->withWorkflowStatusFilter(...$workflowStatuses);
    }
}
