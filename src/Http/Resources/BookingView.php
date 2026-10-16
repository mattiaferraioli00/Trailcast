<?php

namespace App\Http\Resources;

use App\Entity\Bookings;
use OpenApi\Attributes as OA;

readonly class BookingView
{
    #[OA\Property(description: 'Id of the booking.', example: 1)]
    public int $id;

    #[OA\Property(description: 'Id of the booked excursion.', example: 1)]
    public int $excursion_id;

    #[OA\Property(example: 'John Doe')]
    public string $customer_name;

    #[OA\Property(format: 'email', example: 'john.doe@example.com')]
    public string $customer_email;

    #[OA\Property(description: 'Number of seats booked.', example: 3, minimum: 1)]
    public int $seats;

    public function __construct(Bookings $booking)
    {
        $this->id = $booking->getId();
        $this->excursion_id = $booking->getExcursion()->getId();
        $this->customer_name = $booking->getName();
        $this->customer_email = $booking->getEmail();
        $this->seats = $booking->getSeats();
    }
}
