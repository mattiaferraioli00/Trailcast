<?php

namespace App\Http\Requests;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['customer_name', 'customer_email', 'seats'])]
class BookingExcursionRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[OA\Property(description: 'Name of the customer making the booking.', example: 'John Doe')]
    public string $customerName;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Email]
    #[OA\Property(description: 'Email of the customer.', format: 'email', example: 'john.doe@example.com')]
    public string $customerEmail;

    #[Assert\GreaterThan(0)]
    #[OA\Property(description: 'Number of seats to book.', example: 3, minimum: 1)]
    public int $seats;

    public function __construct(
        string $customerName,
        string $customerEmail,
        int $seats
    )
    {
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->seats = $seats;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getSeats(): int
    {
        return $this->seats;
    }
}
