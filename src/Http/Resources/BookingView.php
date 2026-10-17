<?php

namespace App\Http\Resources;

use App\Entity\Bookings;
use OpenApi\Attributes as OA;

readonly class BookingView
{
    #[OA\Property(description: 'Id of the booking.', example: 1)]
    public int $id;

    #[OA\Property(description: 'Id of the booked excursion.', example: 1)]
    public int $excursionId;

    #[OA\Property(example: 'John Doe')]
    public string $customerName;

    #[OA\Property(format: 'email', example: 'john.doe@example.com')]
    public string $customerEmail;

    #[OA\Property(description: 'Number of seats booked.', example: 3, minimum: 1)]
    public int $seats;

    public function __construct(Bookings $booking)
    {
        $this->id = $booking->getId();
        $this->excursionId = $booking->getExcursion()->getId();
        $this->customerName = $booking->getName();
        $this->customerEmail = $booking->getEmail();
        $this->seats = $booking->getSeats();
    }
}
