<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\DistanceFactoryInterface;

class MockDistanceFactory implements DistanceFactoryInterface
{
    /**
     * @param string $distance
     * @return MockDistance
     */
    public function fromString($distance)
    {
        return new MockDistance($distance);
    }
}
