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
            'creator',
            'labels',
            'textLanguages',
        ];
    }
}
