<?php

namespace App\Http\Resources;

use App\Entity\Bookings;

readonly class BookingView
{
    public int $id;
    public string $name;
    public string $email;
    public string $seats;
    public string $excursion_id;

    public function __construct(Bookings $booking)
    {
        $this->id = $booking->getId();
        $this->name = $booking->getName();
        $this->email = $booking->getEmail();
        $this->seats = $booking->getSeats();
        $this->excursion_id = $booking->getExcursion()->getId();
    }
}
