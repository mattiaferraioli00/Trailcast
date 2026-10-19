<?php

namespace App\Http\Requests;

use DateTime;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

class SearchExcursionRequest
{


    #[Assert\Length(max: 255)]
    public ?string $city = null;

    #[Assert\Country]
    public ?string $country = null;

    #[Context([DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
    #[Assert\GreaterThanOrEqual('today')]
    public ?DateTime $dateFrom = null;

    #[Context([DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
    #[Assert\GreaterThanOrEqual(propertyPath: 'dateFrom')]
    public ?DateTime $dateTo = null;

    #[Assert\Positive]
    public ?int $seats = null;

    #[Assert\Positive]
    public readonly int $page;

    #[Assert\Range(min: 1, max: 50)]
    public readonly int $perPage;

    public function __construct(
        ?string $city = null,
        ?string $country = null,
        ?DateTime $dateFrom = null,
        ?DateTime $dateTo = null,
        ?int $seats = null,
        int $page = 1,
        int $perPage = 20,
    ) {
        $this->city = $city;
        $this->country = $country;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->seats = $seats;
        $this->page = $page;
        $this->perPage = $perPage;
    }

    /**
     * @return string|null Exact city of the excursions, as stored at creation; null for any city.
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * @return string|null ISO 3166-1 alpha-2 country code of the excursions; null for any country.
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @return DateTime|null Earliest excursion date, inclusive (before dateTo when both are set); null for no lower bound.
     */
    public function getDateFrom(): ?DateTime
    {
        return $this->dateFrom;
    }

    /**
     * @return DateTime|null Latest excursion date, inclusive (after dateFrom when both are set); null for no upper bound.
     */
    public function getDateTo(): ?DateTime
    {
        return $this->dateTo;
    }

    /**
     * @return int|null Minimum number of available seats (greater than 0); null for any excursion with at least one seat.
     */
    public function getSeats(): ?int
    {
        return $this->seats;
    }

    /**
     * @return int Page number, starting from 1.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @return int Number of excursions per page (1 to 50, default 20).
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }
}
