<?php

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Search\Offer\WorkflowStatus;
use Symfony\Component\HttpFoundation\Request;

class ParameterBagParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ParameterBagParser
     */
    private $parser;

    public function setUp()
    {
        $this->parser = new ParameterBagParser();
    }

    /**
     * @test
     * @dataProvider arrayQueryParameterDataProvider
     *
     * @param array $queryParameters
     * @param string $parameterName
     * @param array $expectedScalarValues
     */
    public function it_should_parse_a_query_parameter_as_an_array(
        array $queryParameters,
        $parameterName,
        array $expectedScalarValues
    ) {
        $request = new Request($queryParameters);
        $actualScalarValues = $this->parser->getArrayFromQueryParameter($request, $parameterName);
        $this->assertEquals($expectedScalarValues, $actualScalarValues);
    }

    /**
     * @test
     * @dataProvider arrayQueryParameterDataProvider
     *
     * @param array $queryParameters
     * @param string $parameterName
     * @param array $expectedScalarValues
     * @param callable $callback
     * @param array $expectedCastedValues
     */
    public function it_should_apply_an_optional_callback_to_each_value_of_a_query_parameter(
        array $queryParameters,
        $parameterName,
        array $expectedScalarValues,
        callable $callback,
        array $expectedCastedValues
    ) {
        $request = new Request($queryParameters);
        $actualCastedValues = $this->parser->getArrayFromQueryParameter($request, $parameterName, $callback);
        $this->assertEquals($expectedCastedValues, $actualCastedValues);
    }

    /**
     * @return array
     */
    public function arrayQueryParameterDataProvider()
    {
        $callback = function ($label) {
            return new LabelName($label);
        };

        return [
            [
                'query' => ['labels' => ['UiTPASLeuven', 'Paspartoe']],
                'parameter' => 'labels',
                'values' => ['UiTPASLeuven', 'Paspartoe'],
                'callback' => $callback,
                'casted' => [new LabelName('UiTPASLeuven'), new LabelName('Paspartoe')],
            ],
            [
                'query' => ['labels' => ['UiTPASLeuven']],
                'parameter' => 'labels',
                'values' => ['UiTPASLeuven'],
                'callback' => $callback,
                'casted' => [new LabelName('UiTPASLeuven')],
            ],
            [
                'query' => ['labels' => 'UiTPASLeuven'],
                'parameter' => 'labels',
                'values' => ['UiTPASLeuven'],
                'callback' => $callback,
                'casted' => [new LabelName('UiTPASLeuven')],
            ],
            [
                'query' => ['labels' => []],
                'parameter' => 'labels',
                'values' => [],
                'callback' => $callback,
                'casted' => [],
            ],
            [
                'query' => ['labels' => null],
                'parameter' => 'labels',
                'values' => [],
                'callback' => $callback,
                'casted' => [],
            ],
            [
                'query' => [],
                'parameter' => 'labels',
                'values' => [],
                'callback' => $callback,
                'casted' => [],
            ],
        ];
    }

    /**
     * @test
     */
    public function it_should_parse_a_query_parameter_as_a_single_string()
    {
        $request = new Request(['workflowStatus' => 'DRAFT']);
        $expected = 'DRAFT';
        $actual = $this->parser->getStringFromQueryParameter($request, 'workflowStatus');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_apply_a_callback_to_the_single_string_value()
    {
        $request = new Request(['workflowStatus' => 'DRAFT']);

        $callback = function ($workflowStatus) {
            return new WorkflowStatus($workflowStatus);
        };

        $expected = new WorkflowStatus('DRAFT');
        $actual = $this->parser->getStringFromQueryParameter($request, 'workflowStatus', null, $callback);

        $this->assertTrue($expected->sameValueAs($actual));
    }

    /**
     * @test
     * @codingStandardsIgnoreStart
     */
    public function it_should_return_a_default_for_a_single_string_parameter_if_it_is_empty_and_a_default_is_available_and_defaults_are_enabled()
    {
        // @codingStandardsIgnoreEnd

        $request = new Request();
        $default = 'APPROVED';

        $expected = 'APPROVED';
        $actual = $this->parser->getStringFromQueryParameter($request, 'workflowStatus', $default);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_apply_a_callback_to_the_single_string_default_value()
    {
        $request = new Request();
        $default = 'APPROVED';

        $callback = function ($workflowStatus) {
            return new WorkflowStatus($workflowStatus);
        };

        $expected = new WorkflowStatus('APPROVED');
        $actual = $this->parser->getStringFromQueryParameter($request, 'workflowStatus', $default, $callback);

        $this->assertTrue($expected->sameValueAs($actual));
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_single_string_parameter_if_the_parameter_value_is_a_wildcard()
    {
        $request = new Request(['workflowStatus' => '*']);
        $actual = $this->parser->getStringFromQueryParameter($request, 'workflowStatus');
        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_single_string_parameter_if_it_is_is_empty_and_no_default_is_available()
    {
        $request = new Request([]);
        $actual = $this->parser->getStringFromQueryParameter($request, 'workflowStatus');
        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_single_string_parameter_if_it_is_is_empty_and_defaults_are_disabled()
    {
        $request = new Request(['disableDefaultFilters' => true]);
        $default = 'APPROVED';

        $actual = $this->parser->getStringFromQueryParameter($request, 'workflowStatus', $default);

        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_parse_a_query_parameter_as_a_delimited_string_and_return_an_array()
    {
        $request = new Request(['workflowStatus' => 'READY_FOR_VALIDATION,APPROVED']);
        $expected = ['READY_FOR_VALIDATION', 'APPROVED'];
        $actual = $this->parser->getDelimitedStringFromQueryParameter($request, 'workflowStatus');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_apply_a_callback_to_each_value_of_the_delimited_string_array()
    {
        $request = new Request(['workflowStatus' => 'READY_FOR_VALIDATION,APPROVED']);

        $callback = function ($workflowStatus) {
            return new WorkflowStatus($workflowStatus);
        };

        $expected = [new WorkflowStatus('READY_FOR_VALIDATION'), new WorkflowStatus('APPROVED')];
        $actual = $this->parser->getDelimitedStringFromQueryParameter($request, 'workflowStatus', null, $callback);

        $this->assertArrayContentsAreEqual($expected, $actual);
    }

    /**
     * @test
     * @codingStandardsIgnoreStart
     */
    public function it_should_return_a_default_as_an_array_for_a_delimited_string_parameter_if_it_is_empty_and_a_default_is_available_and_defaults_are_enabled()
    {
        // @codingStandardsIgnoreEnd

        $request = new Request();
        $default = 'READY_FOR_VALIDATION,APPROVED';

        $expected = ['READY_FOR_VALIDATION', 'APPROVED'];
        $actual = $this->parser->getDelimitedStringFromQueryParameter($request, 'workflowStatus', $default);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_apply_a_callback_to_each_value_of_the_delimited_string_default_array()
    {
        $request = new Request();
        $default = 'READY_FOR_VALIDATION,APPROVED';

        $callback = function ($workflowStatus) {
            return new WorkflowStatus($workflowStatus);
        };

        $expected = [new WorkflowStatus('READY_FOR_VALIDATION'), new WorkflowStatus('APPROVED')];
        $actual = $this->parser->getDelimitedStringFromQueryParameter($request, 'workflowStatus', $default, $callback);

        $this->assertArrayContentsAreEqual($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_an_empty_array_for_a_delimited_string_parameter_if_the_string_value_is_a_wildcard()
    {
        $request = new Request(['workflowStatus' => '*']);
        $expected = [];
        $actual = $this->parser->getDelimitedStringFromQueryParameter($request, 'workflowStatus');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * @codingStandardsIgnoreStart
     */
    public function it_should_return_an_empty_array_for_a_delimited_string_parameter_if_it_is_is_empty_and_no_default_is_available()
    {
        // @codingStandardsIgnoreEnd

        $request = new Request([]);
        $expected = [];
        $actual = $this->parser->getDelimitedStringFromQueryParameter($request, 'workflowStatus');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * @codingStandardsIgnoreStart
     */
    public function it_should_return_an_empty_array_for_a_delimited_string_parameter_if_it_is_is_empty_and_defaults_are_disabled()
    {
        // @codingStandardsIgnoreEnd

        $request = new Request(['disableDefaultFilters' => true]);
        $default = 'READY_FOR_VALIDATION,APPROVED';

        $expected = [];
        $actual = $this->parser->getDelimitedStringFromQueryParameter($request, 'workflowStatus', $default);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * @dataProvider booleanDataProvider
     *
     * @param mixed $queryParameterValue
     * @param bool|null $expectedValue
     */
    public function it_should_parse_a_boolean_value_from_a_query_parameter(
        $queryParameterValue,
        $expectedValue
    ) {
        $request = new Request(['uitpas' => $queryParameterValue]);
        $actualValue = $this->parser->getBooleanFromQueryParameter($request, 'uitpas');
        $this->assertTrue($expectedValue === $actualValue);
    }

    /**
     * @return Request[]
     */
    public function booleanDataProvider()
    {
        return [
            [
                false,
                false,
            ],
            [
                true,
                true,
            ],
            [
                'false',
                false,
            ],
            [
                'FALSE',
                false,
            ],
            [
                '0',
                false,
            ],
            [
                0,
                false,
            ],
            [
                'true',
                true,
            ],
            [
                'TRUE',
                true,
            ],
            [
                '1',
                true,
            ],
            [
                1,
                true,
            ],
            [
                '',
                null,
            ],
            [
                null,
                null,
            ],
        ];
    }

    /**
     * @test
     * @codingStandardsIgnoreStart
     */
    public function it_should_return_a_default_for_a_boolean_parameter_if_it_is_empty_and_a_default_is_available_and_defaults_are_enabled()
    {
        // @codingStandardsIgnoreEnd

        $request = new Request();
        $default = 'true';

        $expected = true;
        $actual = $this->parser->getBooleanFromQueryParameter($request, 'uitpas', $default);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_boolean_parameter_if_the_parameter_value_is_a_wildcard()
    {
        $request = new Request(['uitpas' => '*']);
        $actual = $this->parser->getBooleanFromQueryParameter($request, 'uitpas');
        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_boolean_parameter_if_it_is_is_empty_and_no_default_is_available()
    {
        $request = new Request([]);
        $actual = $this->parser->getStringFromQueryParameter($request, 'uitpas');
        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_boolean_parameter_if_it_is_is_empty_and_defaults_are_disabled()
    {
        $request = new Request(['disableDefaultFilters' => true]);
        $default = true;

        $actual = $this->parser->getBooleanFromQueryParameter($request, 'uitpas', $default);

        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_parse_a_datetime_from_a_query_parameter()
    {
        $request = new Request(['availableFrom' => '2017-04-26T12:20:05+01:00']);
        $expected = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-04-26T12:20:05+01:00');
        $actual = $this->parser->getDateTimeFromQueryParameter($request, 'availableFrom');

        $this->assertDateTimeEquals($expected, $actual);
    }

    /**
     * @test
     * @codingStandardsIgnoreStart
     */
    public function it_should_return_a_default_for_a_datetime_parameter_if_it_is_empty_and_a_default_is_available_and_defaults_are_enabled()
    {
        // @codingStandardsIgnoreEnd

        $request = new Request();
        $default = '2017-04-26T12:20:05+01:00';

        $expected = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2017-04-26T12:20:05+01:00');
        $actual = $this->parser->getDateTimeFromQueryParameter($request, 'availableFrom', $default);

        $this->assertDateTimeEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_datetime_parameter_if_the_parameter_value_is_a_wildcard()
    {
        $request = new Request(['availableFrom' => '*']);
        $actual = $this->parser->getDateTimeFromQueryParameter($request, 'availableFrom');
        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_datetime_parameter_if_it_is_is_empty_and_no_default_is_available()
    {
        $request = new Request([]);
        $actual = $this->parser->getDateTimeFromQueryParameter($request, 'availableFrom');
        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_for_a_datetime_parameter_if_it_is_is_empty_and_defaults_are_disabled()
    {
        $request = new Request(['disableDefaultFilters' => true]);
        $default = '2017-04-26T12:20:05+01:00';

        $actual = $this->parser->getDateTimeFromQueryParameter($request, 'availableFrom', $default);

        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_throw_an_exception_if_a_datetime_parameter_can_not_be_parsed()
    {
        $request = new Request(['availableFrom' => '26/04/2017']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'availableFrom should be an ISO-8601 datetime, for example 2017-04-26T12:20:05+01:00'
        );

        $this->parser->getDateTimeFromQueryParameter($request, 'availableFrom');
    }

    /**
     * @param array $expected
     * @param array $actual
     */
    private function assertArrayContentsAreEqual(array $expected, array $actual)
    {
        $this->assertCount(count($expected), $actual);

        foreach ($expected as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $actual[$key]);
        }
    }

    /**
     * @param \DateTimeImmutable $expected
     * @param \DateTimeImmutable $actual
     */
    private function assertDateTimeEquals(\DateTimeImmutable $expected, \DateTimeImmutable $actual)
    {
        $this->assertEquals(
            $expected->format(\DateTime::ATOM),
            $actual->format(\DateTime::ATOM)
        );
    }
}
