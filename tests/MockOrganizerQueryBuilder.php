<?php

declare(strict_types=1);

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Search\AbstractQueryString;
use CultuurNet\UDB3\Search\Creator;
use CultuurNet\UDB3\Search\Organizer\OrganizerQueryBuilderInterface;
use CultuurNet\UDB3\Search\Organizer\WorkflowStatus;
use ValueObjects\Geography\Country;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;
use ValueObjects\Web\Domain;
use ValueObjects\Web\Url;

final class MockOrganizerQueryBuilder implements OrganizerQueryBuilderInterface
{
    private $mockQuery = [];

    public function __construct()
    {
        $this->mockQuery['limit'] = 30;
        $this->mockQuery['start'] = 0;
    }

    public function withAutoCompleteFilter(StringLiteral $input)
    {
        $c = clone $this;
        $c->mockQuery['autoComplete'] = (string) $input;
        return $c;
    }

    public function withWebsiteFilter(Url $url)
    {
        $c = clone $this;
        $c->mockQuery['website'] = (string) $url;
        return $c;
    }

    public function withDomainFilter(Domain $domain)
    {
        $c = clone $this;
        $c->mockQuery['domain'] = (string) $domain;
        return $c;
    }

    public function withPostalCodeFilter(PostalCode $postalCode)
    {
        $c = clone $this;
        $c->mockQuery['postalCode'] = (string) $postalCode;
        return $c;
    }

    public function withAddressCountryFilter(Country $country)
    {
        $c = clone $this;
        $c->mockQuery['country'] = (string) $country;
        return $c;
    }

    public function withCreatorFilter(Creator $creator)
    {
        $c = clone $this;
        $c->mockQuery['creator'] = (string) $creator;
        return $c;
    }

    public function withLabelFilter(LabelName $label)
    {
        $c = clone $this;
        $c->mockQuery['label'][] = (string) $label;
        return $c;
    }

    public function withWorkflowStatusFilter(WorkflowStatus ...$workflowStatuses)
    {
        $c = clone $this;
        $c->mockQuery['workflowStatus'] = array_map(function (WorkflowStatus $workflowStatus) {
            return (string) $workflowStatus;
        }, $workflowStatuses);
        return $c;
    }

    public function withAdvancedQuery(AbstractQueryString $queryString, Language ...$textLanguages)
    {
        $c = clone $this;
        $c->mockQuery['advancedQuery']['query'] = (string) $queryString;
        $c->mockQuery['advancedQuery']['language'] = array_map(function (Language $language) {
            return (string) $language;
        }, $textLanguages);
        return $c;
    }

    public function withTextQuery(StringLiteral $text, Language ...$textLanguages)
    {
        $c = clone $this;
        $c->mockQuery['textQuery']['query'] = (string) $text;
        $c->mockQuery['textQuery']['language'] = array_map(function (Language $language) {
            return (string) $language;
        }, $textLanguages);
        return $c;
    }

    public function withStart(Natural $start)
    {
        $c = clone $this;
        $c->mockQuery['start'] = $start->toNative();
        return $c;
    }

    public function withLimit(Natural $limit)
    {
        $c = clone $this;
        $c->mockQuery['limit'] = $limit->toNative();
        return $c;
    }

    public function build()
    {
        $build = $this->mockQuery;
        ksort($build);
        return $build;
    }
}
