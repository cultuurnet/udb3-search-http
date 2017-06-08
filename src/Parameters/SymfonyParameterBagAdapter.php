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

        $values = (array) $this->parameterBag->get($queryParameter);

        if (!is_null($callback)) {
            $values = array_map($callback, $values);
        }

        return $values;
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
        $defaultsEnabled = $this->areDefaultFiltersEnabled();
        $callback = $this->ensureCallback($callback);

        if ($parameterValue === $this->resetValue ||
            is_null($parameterValue) && (is_null($defaultValue) || !$defaultsEnabled)) {
            return null;
        }

        if (is_null($parameterValue)) {
            $parameterValue = $defaultValue;
        }

        return call_user_func($callback, $parameterValue);
    }

    /**
     * @param string $parameterName
     * @param string|null $defaultValueAsString
     * @param callable|null $callback
     * @param string $delimiter
     * @return array
     */
    public function getDelimitedStringFromParameter(
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
        $callback = function ($bool) {
            // This is a private method so we can't pass it directly as the
            // callback method.
            return $this->castMixedToBool($bool);
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
        $disabled = $this->castMixedToBool($this->parameterBag->get('disableDefaultFilters', false));
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

    /**
     * @param mixed $mixed
     * @return bool|null
     */
    private function castMixedToBool($mixed)
    {
        if (is_null($mixed) || (is_string($mixed) && strlen($mixed) === 0)) {
            return null;
        }

        return filter_var($mixed, FILTER_VALIDATE_BOOLEAN);
    }
}
