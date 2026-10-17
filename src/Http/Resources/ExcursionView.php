<?php

namespace App\Http\Resources;

use App\Entity\Excursion;
use OpenApi\Attributes as OA;

readonly class ExcursionView
{
    #[OA\Property(example: 1)]
    public int $id;

    #[OA\Property(example: 'Sunrise hike on Etna')]
    public string $title;

    #[OA\Property(example: 'Guided ascent with a stop at the Valle del Bove')]
    public string $description;

    #[OA\Property(example: 'Catania')]
    public string $city;

    #[OA\Property(description: 'ISO 3166-1 alpha-2 country code.', example: 'IT')]
    public string $country;

    #[OA\Property(format: 'date', example: '2026-11-20')]
    public string $date;

    #[OA\Property(description: 'Total number of seats.', example: 20)]
    public int $capacity;

    #[OA\Property(description: 'Seats still available.', example: 7)]
    public int $seatsAvailable;

    #[OA\Property(description: 'Latitude resolved from the city by geocoding.', example: 37.49223)]
    public float $latitude;

    #[OA\Property(description: 'Longitude resolved from the city by geocoding.', example: 15.07041)]
    public float $longitude;

    public function __construct(Excursion $excursion)
    {
        $this->id = $excursion->getId();
        $this->title = $excursion->getTitle();
        $this->description = $excursion->getDescription();
        $this->city = $excursion->getCity();
        $this->country = $excursion->getCountry();
        $this->date = $excursion->getDate()->format('Y-m-d');
        $this->capacity = $excursion->getCapacity();
        $this->seatsAvailable = $excursion->getSeatsAvailable();
        $this->latitude = $excursion->getLatitude();
        $this->longitude = $excursion->getLongitude();
    }
}
