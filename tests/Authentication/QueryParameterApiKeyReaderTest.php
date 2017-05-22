<?php

namespace CultuurNet\UDB3\Search\Http\Authentication;

use CultuurNet\UDB3\Search\Authentication\ApiKey;
use Symfony\Component\HttpFoundation\Request;

class QueryParameterApiKeyReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CustomHeaderApiKeyReader
     */
    private $reader;

    public function setUp()
    {
        $this->reader = new QueryParameterApiKeyReader('apiKey');
    }

    /**
     * @test
     */
    public function it_should_return_null_if_the_configured_query_parameter_is_not_set()
    {
        $request = Request::create('https://search.uitdatabank.be', 'GET');
        $this->assertNull($this->reader->read($request));
    }

    /**
     * @test
     */
    public function it_should_return_null_if_the_configured_query_parameter_is_an_empty_string()
    {
        $request = Request::create(
            'https://search.uitdatabank.be',
            'GET',
            ['apiKey' => '']
        );

        $this->assertNull($this->reader->read($request));
    }

    /**
     * @test
     */
    public function it_should_return_the_api_key_as_a_value_object_if_the_query_parameter_is_set_and_not_empty()
    {
        $expected = new ApiKey('4f3024ab-cfbb-40a0-848c-cb88ee999987');

        $request = Request::create(
            'https://search.uitdatabank.be',
            'GET',
            ['apiKey' => '4f3024ab-cfbb-40a0-848c-cb88ee999987']
        );

        $this->assertEquals($expected, $this->reader->read($request));
    }
}
