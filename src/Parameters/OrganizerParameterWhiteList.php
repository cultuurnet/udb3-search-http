<?php

namespace CultuurNet\UDB3\Search\Http\Parameters;

class OrganizerParameterWhiteList extends AbstractParameterWhiteList
{
    /**
     * @inheritdoc
     */
    protected function getParameterWhiteList()
    {
        return [
            'q',
            'name',
            'website',
            'domain',
            'postalCode',
            'addressCountry',
            'creator',
            'labels',
            'textLanguages',
            'workflowStatus',
            'disableDefaultFilters',
        ];
    }
}
