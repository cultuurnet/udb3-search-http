<?php

namespace CultuurNet\UDB3\Search\Http\Parameters;

use Symfony\Component\HttpFoundation\ParameterBag;

class SymfonyParameterBagAdapter implements ParameterBagInterface
{
    /**
     * @var ParameterBag
     */
    private $parameterBag;

    /**
     * @var string
     */
    private $resetValue;

    /**
     * @param ParameterBag $parameterBag
     * @param string $resetValue
     */
    public function __construct(ParameterBag $parameterBag, $resetValue = '*')
    {
        $this->parameterBag = $parameterBag;
        $this->resetValue = $resetValue;
    }

    /**
     * @param string $queryParameter
     * @param callable|null $callback
     * @return array
     */
    public function getArrayFromParameter($queryParameter, callable $callback = null)
    {
        if (empty($this->parameterBag->get($queryParameter))) {
            return [];
        }

        $callback = $this->ensureCallback($callback);
        $values = (array) $this->parameterBag->get($queryParameter);

        return array_map($callback, $values);
    }

    /**
     * @param string $parameterName
     * @param string|null $defaultValue
     * @param callable $callback
     * @return mixed|null
     */
    public function getStringFromParameter(
        $parameterName,
        $defaultValue = null,
        callable $callback = null
    ) {
        $parameterValue = $this->parameterBag->get($parameterName, null);
        $callback = $this->ensureCallback($callback);

        if (is_array($parameterValue)) {
            throw new \InvalidArgumentException(
                "The parameter \"{$parameterName}\" can only have a single value."
            );
        }

        if ($parameterValue === $this->resetValue) {
            return null;
        }

        if (is_null($parameterValue) && !is_null($defaultValue) && $this->areDefaultFiltersEnabled()) {
            $parameterValue = $defaultValue;
        }

        if (is_null($parameterValue)) {
            return null;
        }

        return call_user_func($callback, $parameterValue);
    }

    /**
     * @param string $parameterName
     * @param string|null $defaultValue
     * @param callable $callback
     * @return mixed|null
     */
    public function getIntegerFromParameter(
        $parameterName,
        $defaultValue = null,
        callable $callback = null
    ) {
        $callback = $this->ensureCallback($callback);

        $intCallback = function ($value) use ($callback) {
            $int = (int) $value;
            return call_user_func($callback, $int);
        };

        return $this->getStringFromParameter($parameterName, $defaultValue, $intCallback);
    }

    /**
     * @param string $parameterName
     * @param string|null $defaultValueAsString
     * @param callable|null $callback
     * @param string $delimiter
     * @return array
     */
    public function getExplodedStringFromParameter(
        $parameterName,
        $defaultValueAsString = null,
        callable $callback = null,
        $delimiter = ','
    ) {
        $callback = $this->ensureCallback($callback);

        $asString = $this->getStringFromParameter(
            $parameterName,
            $defaultValueAsString
        );

        if (is_null($asString)) {
            return [];
        }

        $asArray = explode($delimiter, $asString);

        return array_map($callback, $asArray);
    }

    /**
     * @param string $parameterName
     * @param string|null $defaultValueAsString
     * @return bool|null
     */
    public function getBooleanFromParameter(
        $parameterName,
        $defaultValueAsString = null
    ) {
        $callback = function ($mixed) {
            if (is_null($mixed) || (is_string($mixed) && strlen($mixed) === 0)) {
                return null;
            }

            return filter_var($mixed, FILTER_VALIDATE_BOOLEAN);
        };

        return $this->getStringFromParameter($parameterName, $defaultValueAsString, $callback);
    }

    /**
     * @param string $queryParameter
     * @param string|null $defaultValueAsString
     * @return \DateTimeImmutable|null
     */
    public function getDateTimeFromParameter($queryParameter, $defaultValueAsString = null)
    {
        $callback = function ($asString) use ($queryParameter) {
            $asDateTime = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $asString);

            if (!$asDateTime) {
                throw new \InvalidArgumentException(
                    "{$queryParameter} should be an ISO-8601 datetime, for example 2017-04-26T12:20:05+01:00"
                );
            }

            return $asDateTime;
        };

        return $this->getStringFromParameter($queryParameter, $defaultValueAsString, $callback);
    }

    /**
     * @return bool
     */
    private function areDefaultFiltersEnabled()
    {
        // Don't pass a default value here as it will cause an infinite loop.
        $disabled = $this->getBooleanFromParameter('disableDefaultFilters');

        // Instead check if the returned value is null, and if so always set it
        // to false as it means the disableDefaultFilters parameter is not set.
        $disabled = is_null($disabled) ? false : $disabled;

        return !$disabled;
    }

    /**
     * @param callable|null $callback
     * @return callable
     */
    private function ensureCallback(callable $callback = null)
    {
        if (!is_null($callback)) {
            return $callback;
        }

        $passthroughCallback = function ($value) {
            return $value;
        };

        return $passthroughCallback;
    }
}
