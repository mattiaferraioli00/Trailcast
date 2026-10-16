<?php

namespace App\Http\Requests;

use DateTime;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['title', 'description', 'city', 'country', 'date', 'capacity'])]
class CreateExcursionRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[OA\Property(example: 'Sunrise hike on Etna')]
    public string $title;

    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[OA\Property(example: 'Guided ascent with a stop at the Valle del Bove')]
    public string $description;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[OA\Property(
        description: 'City name, resolved to coordinates with the Open-Meteo geocoding API (exact match on the Italian name).',
        example: 'Catania',
    )]
    public string $city;

    #[Assert\NotBlank]
    #[Assert\Country]
    #[OA\Property(description: 'ISO 3166-1 alpha-2 country code.', pattern: '^[A-Z]{2}$', example: 'IT')]
    public string $country;

    #[Context([DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
    #[Assert\GreaterThan('today')]
    #[OA\Property(description: 'Date of the excursion, after today.', type: 'string', format: 'date', example: '2026-11-20')]
    public DateTime $date;

    #[Assert\Positive]
    #[OA\Property(description: 'Total number of seats.', example: 20, minimum: 1)]
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

    /**
     * @return string Title of the excursion (not blank, max 255 characters).
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string Description of the excursion (not blank, max 500 characters).
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string City where the excursion takes place (not blank, max 255 characters),
     *                resolved to coordinates when the excursion is created.
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @return string ISO 3166-1 alpha-2 country code of the city.
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @return DateTime Date of the excursion (YYYY-MM-DD in the payload, in the future).
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * @return int Total number of seats of the excursion (greater than 0).
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }
}
