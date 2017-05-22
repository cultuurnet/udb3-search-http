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
            'embed',
            'start',
            'limit',
            'name',
            'website'
        ];
    }
}
