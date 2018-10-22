<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Search\Http\Parameters\ParameterBagInterface;
use ValueObjects\Geography\Country;
use ValueObjects\Geography\CountryCode;

class CountryExtractor
{
    /**
     * @param ParameterBagInterface $parameterBag
     * @param null|CountryCode $defaultCountryCode
     * @return null|Country
     */
    public function getCountryFromQuery(
        ParameterBagInterface $parameterBag,
        ?CountryCode $defaultCountryCode
    ): ?Country {
        return $parameterBag->getStringFromParameter(
            'addressCountry',
            null !== $defaultCountryCode ? $defaultCountryCode->toNative() : null,
            function ($country) use ($defaultCountryCode) {
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
