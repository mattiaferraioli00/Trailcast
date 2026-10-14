<?php

namespace App\Http\Requests;

use DateTime;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

class SearchExcursionRequest
{

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $city = null;

    #[Assert\NotBlank]
    #[Assert\Country]
    public ?string $country = null;

    #[Context([DateTimeNormalizer::FORMAT_KEY => 'd-m-Y'])]
    #[Assert\GreaterThan('today')]
    #[Assert\LessThan(propertyPath: 'dateTo')]
    public ?DateTime $dateFrom = null;

    #[Context([DateTimeNormalizer::FORMAT_KEY => 'd-m-Y'])]
    #[Assert\GreaterThan(propertyPath: 'dateFrom')]
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
        ?string $dateFrom = null,
        ?string $dateTo = null,
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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getDateFrom(): ?DateTime
    {
        return $this->dateFrom;
    }

    public function getDateTo(): ?DateTime
    {
        return $this->dateTo;
    }

    public function getSeats(): ?int
    {
        return $this->seats;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }
}
