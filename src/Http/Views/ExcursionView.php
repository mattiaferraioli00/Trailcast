<?php

namespace App\Http\Views;

use App\Entity\Excursion;

class ExcursionView
{
    public int $id;
    public string $title;
    public string $description;
    public string $city;
    public string $country;
    public string $date;
    public int $capacity;

    public float $latitude;
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
        $this->latitude = $excursion->getLatitude();
        $this->longitude = $excursion->getLongitude();
    }
}
