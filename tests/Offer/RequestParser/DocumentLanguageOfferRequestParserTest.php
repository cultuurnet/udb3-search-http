<?php

namespace CultuurNet\UDB3\Search\Http\Offer\RequestParser;

use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

class DocumentLanguageOfferRequestParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DocumentLanguageOfferRequestParser
     */
    private $parser;

    /**
     * @var OfferQueryBuilderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $queryBuilder;

    public function setUp()
    {
        $this->parser = new DocumentLanguageOfferRequestParser();
        $this->queryBuilder = $this->createMock(OfferQueryBuilderInterface::class);
    }

    /**
     * @test
     */
    public function it_should_add_a_main_language_filter()
    {
        $request = new Request(
            [
                'mainLanguage' => 'nl',
            ]
        );

        $this->queryBuilder->expects($this->once())
            ->method('withMainLanguageFilter')
            ->with(new Language('nl'))
            ->willReturn($this->queryBuilder);

        $this->parser->parse($request, $this->queryBuilder);
    }

    /**
     * @test
     */
    public function it_should_add_language_filters()
    {
        $request = new Request(
            [
                'languages' => ['nl', 'fr', 'de'],
            ]
        );

        $this->queryBuilder->expects($this->exactly(3))
            ->method('withLanguageFilter')
            ->withConsecutive(
                [new Language('nl')],
                [new Language('fr')],
                [new Language('de')]
            )
            ->willReturn($this->queryBuilder);

        $this->parser->parse($request, $this->queryBuilder);
    }

    /**
     * @test
     */
    public function it_should_add_completed_language_filters()
    {
        $request = new Request(
            [
                'completedLanguages' => ['nl', 'fr', 'de'],
            ]
        );

        $this->queryBuilder->expects($this->exactly(3))
            ->method('withCompletedLanguageFilter')
            ->withConsecutive(
                [new Language('nl')],
                [new Language('fr')],
                [new Language('de')]
            )
            ->willReturn($this->queryBuilder);

        $this->parser->parse($request, $this->queryBuilder);
    }
}
