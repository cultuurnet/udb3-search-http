<?php

namespace CultuurNet\UDB3\Search\Http;

use Symfony\Component\HttpFoundation\Request;

class ParameterBagParser
{
    /**
     * @var string
     */
    private $resetValue;

    /**
     * @param string $resetValue
     */
    public function __construct($resetValue = '*')
    {
        $this->resetValue = $resetValue;
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @param callable|null $callback
     * @return array
     */
    public function getArrayFromQueryParameter(Request $request, $queryParameter, callable $callback = null)
    {
        if (empty($request->query->get($queryParameter))) {
            return [];
        }

        $values = (array) $request->query->get($queryParameter);

        if (!is_null($callback)) {
            $values = array_map($callback, $values);
        }

        return $values;
    }

    /**
     * @param Request $request
     * @param string $parameterName
     * @param string|null $defaultValue
     * @param callable $callback
     * @return mixed|null
     */
    public function getStringFromQueryParameter(
        Request $request,
        $parameterName,
        $defaultValue = null,
        callable $callback = null
    ) {
        $parameterValue = $request->query->get($parameterName, null);
        $defaultsEnabled = $this->areDefaultFiltersEnabled($request);
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
     * @param Request $request
     * @param string $parameterName
     * @param string|null $defaultValueAsString
     * @param callable|null $callback
     * @param string $delimiter
     * @return array
     */
    public function getDelimitedStringFromQueryParameter(
        Request $request,
        $parameterName,
        $defaultValueAsString = null,
        callable $callback = null,
        $delimiter = ','
    ) {
        $callback = $this->ensureCallback($callback);

        $asString = $this->getStringFromQueryParameter(
            $request,
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
     * @param Request $request
     * @param string $parameterName
     * @param string|null $defaultValueAsString
     * @return bool|null
     */
    public function getBooleanFromQueryParameter(
        Request $request,
        $parameterName,
        $defaultValueAsString = null
    ) {
        $callback = function ($bool) {
            // This is a private method so we can't pass it directly as the
            // callback method.
            return $this->castMixedToBool($bool);
        };

        return $this->getStringFromQueryParameter($request, $parameterName, $defaultValueAsString, $callback);
    }

    /**
     * @param Request $request
     * @param string $queryParameter
     * @param string|null $defaultValueAsString
     * @return \DateTimeImmutable|null
     */
    public function getDateTimeFromQueryParameter(Request $request, $queryParameter, $defaultValueAsString = null)
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

        return $this->getStringFromQueryParameter($request, $queryParameter, $defaultValueAsString, $callback);
    }

    /**
     * @param Request $request
     * @return bool
     */
    private function areDefaultFiltersEnabled(Request $request)
    {
        $disabled = $this->castMixedToBool($request->query->get('disableDefaultFilters', false));
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
