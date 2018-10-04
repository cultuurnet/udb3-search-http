<?php

namespace CultuurNet\UDB3\Search\Http\Parameters;

abstract class AbstractParameterWhiteList
{
    /**
     * @return string[] The list of parameters on the white list.
     */
    abstract protected function getParameterWhiteList();

    /**
     * @return string[]
     */
    protected function getGlobalWhiteList()
    {
        return [
            'apiKey',
            'embed',
            'start',
            'limit',
            'XDEBUG_SESSION_START',
        ];
    }

    /**
     * @param string[] $parameters
     * @throws \InvalidArgumentException
     */
    public function validateParameters(array $parameters)
    {
        $whiteList = array_merge($this->getGlobalWhiteList(), $this->getParameterWhiteList());
        $unknownParameters = array_diff($parameters, $whiteList);
        if (count($unknownParameters) > 0) {
            throw new \InvalidArgumentException(
                'Unknown query parameter(s): ' . implode(', ', $unknownParameters)
            );
        }
    }
}
