<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\Http\Parameters\ParameterBagInterface;
use ValueObjects\Geography\Country;
use ValueObjects\Geography\CountryCode;

class CountryExtractor
{
    /**
     * @param ParameterBagInterface $parameterBag
     * @return null|Country
     */
    public function getCountryFromQuery(ParameterBagInterface $parameterBag): ?Country
    {
        return $parameterBag->getStringFromParameter(
            'addressCountry',
            'BE',
            function ($country) {
                try {
                    $countryCode = CountryCode::fromNative(strtoupper((string) $country));
                    return new Country($countryCode);
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException("Unknown country code '{$country}'.");
                }
            }
        );
    }
}
