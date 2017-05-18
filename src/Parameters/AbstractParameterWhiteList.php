<?php

namespace CultuurNet\UDB3\Search\Http\Parameters;

abstract class AbstractParameterWhiteList
{
    /**
     * string[] The list of parameters on the white list.
     */
    abstract protected function getParameterWhiteList();

    /**
     * @param string[] $parameters
     * @throws \InvalidArgumentException
     */
    public function validateParameters(array $parameters)
    {
        $unknownParameters = array_diff($parameters, $this->getParameterWhiteList());
        if (count($unknownParameters) > 0) {
            throw new \InvalidArgumentException(
                'Unknown query parameter(s): ' . implode(', ', $unknownParameters)
            );
        }
    }
}
