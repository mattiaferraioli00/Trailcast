<?php

namespace App\Http\Requests;

use DateTime;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

class CreateExcursionRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title;

    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $description;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $city;

    #[Assert\NotBlank]
    #[Assert\Country]
    public string $country;

    #[Context([DateTimeNormalizer::FORMAT_KEY => 'd-m-Y'])]
    #[Assert\GreaterThan('today')]
    public DateTime $date;

    #[Assert\Positive]
    public int $capacity;

    public function __construct(
        string $title,
        string $description,
        string $city,
        string $country,
        DateTime $date,
        int $capacity,
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->city = $city;
        $this->country = $country;
        $this->date = $date;
        $this->capacity = $capacity;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

}
