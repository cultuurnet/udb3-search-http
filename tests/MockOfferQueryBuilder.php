<?php

declare(strict_types=1);

namespace CultuurNet\UDB3\Search\Http;

use CultuurNet\Geocoding\Coordinate\Coordinates;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Label\ValueObjects\LabelName;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\Search\AbstractQueryString;
use CultuurNet\UDB3\Search\Creator;
use CultuurNet\UDB3\Search\GeoBoundsParameters;
use CultuurNet\UDB3\Search\GeoDistanceParameters;
use CultuurNet\UDB3\Search\Offer\AudienceType;
use CultuurNet\UDB3\Search\Offer\CalendarType;
use CultuurNet\UDB3\Search\Offer\Cdbid;
use CultuurNet\UDB3\Search\Offer\FacetName;
use CultuurNet\UDB3\Search\Offer\OfferQueryBuilderInterface;
use CultuurNet\UDB3\Search\Offer\TermId;
use CultuurNet\UDB3\Search\Offer\TermLabel;
use CultuurNet\UDB3\Search\Offer\WorkflowStatus;
use CultuurNet\UDB3\Search\Region\RegionId;
use CultuurNet\UDB3\Search\SortOrder;
use ValueObjects\Geography\Country;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;

final class MockOfferQueryBuilder implements OfferQueryBuilderInterface
{
    private $mockQuery = [];

    public function __construct()
    {
        $this->mockQuery['limit'] = 30;
        $this->mockQuery['start'] = 0;
    }

    public function withCdbIdFilter(Cdbid $cdbid)
    {
        $c = clone $this;
        $c->mockQuery['cdbId'] = (string) $cdbid;
        return $c;
    }

    public function withLocationCdbIdFilter(Cdbid $locationCdbid)
    {
        $c = clone $this;
        $c->mockQuery['locationCdbId'] = (string) $locationCdbid;
        return $c;
    }

    public function withOrganizerCdbIdFilter(Cdbid $organizerCdbId)
    {
        $c = clone $this;
        $c->mockQuery['organizerCdbId'] = (string) $organizerCdbId;
        return $c;
    }

    public function withMainLanguageFilter(Language $mainLanguage)
    {
        $c = clone $this;
        $c->mockQuery['mainLanguage'] = (string) $mainLanguage;
        return $c;
    }

    public function withLanguageFilter(Language $language)
    {
        $c = clone $this;
        $c->mockQuery['language'][] = (string) $language;
        return $c;
    }

    public function withCompletedLanguageFilter(Language $language)
    {
        $c = clone $this;
        $c->mockQuery['completedLanguage'][] = (string) $language;
        return $c;
    }

    public function withAvailableRangeFilter(\DateTimeImmutable $from = null, \DateTimeImmutable $to = null)
    {
        $c = clone $this;
        $c->mockQuery['availableRange']['from'] = $from ? $from->format(DATE_ATOM) : null;
        $c->mockQuery['availableRange']['to'] = $to ? $to->format(DATE_ATOM) : null;
        return $c;
    }

    public function withWorkflowStatusFilter(WorkflowStatus ...$workflowStatuses)
    {
        if (empty($workflowStatuses)) {
            return $this;
        }

        $c = clone $this;
        $c->mockQuery['workflowStatus'] = array_map(function (WorkflowStatus $workflowStatus) {
            return (string) $workflowStatus;
        }, $workflowStatuses);
        return $c;
    }

    public function withCreatedRangeFilter(\DateTimeImmutable $from = null, \DateTimeImmutable $to = null)
    {
        $c = clone $this;
        $c->mockQuery['createdRange']['from'] = $from ? $from->format(DATE_ATOM) : null;
        $c->mockQuery['createdRange']['to'] = $to ? $to->format(DATE_ATOM) : null;
        return $c;
    }

    public function withModifiedRangeFilter(\DateTimeImmutable $from = null, \DateTimeImmutable $to = null)
    {
        $c = clone $this;
        $c->mockQuery['modifiedRange']['from'] = $from ? $from->format(DATE_ATOM) : null;
        $c->mockQuery['modifiedRange']['to'] = $to ? $to->format(DATE_ATOM) : null;
        return $c;
    }

    public function withCreatorFilter(Creator $creator)
    {
        $c = clone $this;
        $c->mockQuery['creator'] = (string) $creator;
        return $c;
    }

    public function withDateRangeFilter(\DateTimeImmutable $from = null, \DateTimeImmutable $to = null)
    {
        $c = clone $this;
        $c->mockQuery['dateRange']['from'] = $from ? $from->format(DATE_ATOM) : null;
        $c->mockQuery['dateRange']['to'] = $to ? $to->format(DATE_ATOM) : null;
        return $c;
    }

    public function withCalendarTypeFilter(CalendarType ...$calendarTypes)
    {
        $c = clone $this;
        $c->mockQuery['calendarType'] = array_map(function (CalendarType $calendarType) {
            return (string) $calendarType;
        }, $calendarTypes);
        return $c;
    }

    public function withPostalCodeFilter(PostalCode $postalCode)
    {
        $c = clone $this;
        $c->mockQuery['postalCode'] = (string) $postalCode;
        return $c;
    }

    public function withAddressCountryFilter(Country $country)
    {
        $c = clone $this;
        $c->mockQuery['country'] = (string) $country;
        return $c;
    }

    public function withRegionFilter(
        StringLiteral $regionIndexName,
        StringLiteral $regionDocumentType,
        RegionId $regionId
    ) {
        $c = clone $this;
        $c->mockQuery['region']['index'] = (string) $regionIndexName;
        $c->mockQuery['region']['type'] = (string) $regionDocumentType;
        $c->mockQuery['region']['id'] = (string) $regionId;
        return $c;
    }

    public function withGeoDistanceFilter(GeoDistanceParameters $geoDistance)
    {
        $c = clone $this;
        $c->mockQuery['geoDistance']['lat'] = $geoDistance->getCoordinates()->getLatitude()->toDouble();
        $c->mockQuery['geoDistance']['lng'] = $geoDistance->getCoordinates()->getLongitude()->toDouble();
        $c->mockQuery['geoDistance']['distance'] = $geoDistance->getMaximumDistance()->toNative();
        return $c;
    }

    public function withGeoBoundsFilter(GeoBoundsParameters $geoBounds)
    {
        $c = clone $this;

        $c->mockQuery['geoBounds']['northWest']['lat'] = $geoBounds->getNorthWestCoordinates()
            ->getLatitude()->toDouble();
        $c->mockQuery['geoBounds']['northWest']['lng'] = $geoBounds->getNorthWestCoordinates()
            ->getLongitude()->toDouble();

        $c->mockQuery['geoBounds']['northEast']['lat'] = $geoBounds->getNorthEastCoordinates()
            ->getLatitude()->toDouble();
        $c->mockQuery['geoBounds']['northEast']['lng'] = $geoBounds->getNorthEastCoordinates()
            ->getLongitude()->toDouble();

        $c->mockQuery['geoBounds']['southWest']['lat'] = $geoBounds->getSouthWestCoordinates()
            ->getLatitude()->toDouble();
        $c->mockQuery['geoBounds']['southWest']['lng'] = $geoBounds->getSouthWestCoordinates()
            ->getLongitude()->toDouble();

        $c->mockQuery['geoBounds']['southEast']['lat'] = $geoBounds->getSouthEastCoordinates()
            ->getLatitude()->toDouble();
        $c->mockQuery['geoBounds']['southEast']['lng'] = $geoBounds->getSouthEastCoordinates()
            ->getLongitude()->toDouble();

        return $c;
    }

    public function withAudienceTypeFilter(AudienceType $audienceType)
    {
        $c = clone $this;
        $c->mockQuery['audienceType'] = (string) $audienceType;
        return $c;
    }

    public function withAgeRangeFilter(Natural $minimum = null, Natural $maximum = null)
    {
        $c = clone $this;
        $c->mockQuery['ageRange']['from'] = $minimum ? $minimum->toNative() : null;
        $c->mockQuery['ageRange']['to'] = $maximum ? $maximum->toNative() : null;
        return $c;
    }

    public function withAllAgesFilter($include)
    {
        $c = clone $this;
        $c->mockQuery['allAgesOnly'] = (bool) $include;
        return $c;
    }

    public function withPriceRangeFilter(Price $minimum = null, Price $maximum = null)
    {
        $c = clone $this;
        $c->mockQuery['priceRange']['from'] = $minimum ? $minimum->toNative() : null;
        $c->mockQuery['priceRange']['to'] = $maximum ? $maximum->toNative() : null;
        return $c;
    }

    public function withMediaObjectsFilter($include)
    {
        $c = clone $this;
        $c->mockQuery['mediaObjects'] = (bool) $include;
        return $c;
    }

    public function withUiTPASFilter($include)
    {
        $c = clone $this;
        $c->mockQuery['uitpas'] = (bool) $include;
        return $c;
    }

    public function withTermIdFilter(TermId $termId)
    {
        $c = clone $this;
        $c->mockQuery['termId'][] = (string) $termId;
        return $c;
    }

    public function withTermLabelFilter(TermLabel $termLabel)
    {
        $c = clone $this;
        $c->mockQuery['termLabel'][] = (string) $termLabel;
        return $c;
    }

    public function withLocationTermIdFilter(TermId $locationTermId)
    {
        $c = clone $this;
        $c->mockQuery['locationTermId'][] = (string) $locationTermId;
        return $c;
    }

    public function withLocationTermLabelFilter(TermLabel $locationTermLabel)
    {
        $c = clone $this;
        $c->mockQuery['locationTermLabel'][] = (string) $locationTermLabel;
        return $c;
    }

    public function withLabelFilter(LabelName $label)
    {
        $c = clone $this;
        $c->mockQuery['label'][] = (string) $label;
        return $c;
    }

    public function withLocationLabelFilter(LabelName $locationLabel)
    {
        $c = clone $this;
        $c->mockQuery['locationLabel'][] = (string) $locationLabel;
        return $c;
    }

    public function withOrganizerLabelFilter(LabelName $organizerLabel)
    {
        $c = clone $this;
        $c->mockQuery['organizerLabel'][] = (string) $organizerLabel;
        return $c;
    }

    public function withFacet(FacetName $facetName)
    {
        $c = clone $this;
        $c->mockQuery['facet'][] = (string) $facetName;
        return $c;
    }

    public function withSortByScore(SortOrder $sortOrder)
    {
        $c = clone $this;
        $c->mockQuery['sort']['score'] = (string) $sortOrder;
        return $c;
    }

    public function withSortByAvailableTo(SortOrder $sortOrder)
    {
        $c = clone $this;
        $c->mockQuery['sort']['availableTo'] = (string) $sortOrder;
        return $c;
    }

    public function withSortByCreated(SortOrder $sortOrder)
    {
        $c = clone $this;
        $c->mockQuery['sort']['created'] = (string) $sortOrder;
        return $c;
    }

    public function withSortByModified(SortOrder $sortOrder)
    {
        $c = clone $this;
        $c->mockQuery['sort']['modified'] = (string) $sortOrder;
        return $c;
    }

    public function withSortByDistance(Coordinates $coordinates, SortOrder $sortOrder)
    {
        $c = clone $this;
        $c->mockQuery['sort']['distance']['lat'] = $coordinates->getLatitude()->toDouble();
        $c->mockQuery['sort']['distance']['lng'] = $coordinates->getLongitude()->toDouble();
        $c->mockQuery['sort']['distance']['order'] = (string) $sortOrder;
        return $c;
    }

    public function withAdvancedQuery(AbstractQueryString $queryString, Language ...$textLanguages)
    {
        $c = clone $this;
        $c->mockQuery['advancedQuery']['query'] = (string) $queryString;
        $c->mockQuery['advancedQuery']['language'] = array_map(function (Language $language) {
            return (string) $language;
        }, $textLanguages);
        return $c;
    }

    public function withTextQuery(StringLiteral $text, Language ...$textLanguages)
    {
        $c = clone $this;
        $c->mockQuery['textQuery']['query'] = (string) $text;
        $c->mockQuery['textQuery']['language'] = array_map(function (Language $language) {
            return (string) $language;
        }, $textLanguages);
        return $c;
    }

    public function withStart(Natural $start)
    {
        $c = clone $this;
        $c->mockQuery['start'] = $start->toNative();
        return $c;
    }

    public function withLimit(Natural $limit)
    {
        $c = clone $this;
        $c->mockQuery['limit'] = $limit->toNative();
        return $c;
    }

    public function build()
    {
        $build = $this->mockQuery;
        ksort($build);
        return $build;
    }
}
