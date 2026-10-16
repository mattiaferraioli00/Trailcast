<?php

namespace App\Http\Requests;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['customer_name', 'customer_email', 'seats'])]
class BookingExcursionRequest
{
    #[SerializedName('customer_name')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[OA\Property(description: 'Name of the customer making the booking.', example: 'John Doe')]
    public string $customerName;

    #[SerializedName('customer_email')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Email]
    #[OA\Property(description: 'Email of the customer.', format: 'email', example: 'john.doe@example.com')]
    public string $customerEmail;

    #[Assert\GreaterThan(0)]
    #[OA\Property(description: 'Number of seats to book.', example: 3, minimum: 1)]
    public int $seats;

    public function __construct(
        string $customer_name,
        string $customer_email,
        int $seats
    )
    {
        $this->customerName = $customer_name;
        $this->customerEmail = $customer_email;
        $this->seats = $seats;
    }

    /**
     * @return string Name of the customer making the booking (not blank, max 255 characters).
     */
    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    /**
     * @return string Email of the customer making the booking (valid address, max 255 characters).
     */
    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    /**
     * @return int Number of seats to book (at least 1).
     */
    public function getSeats(): int
    {
        return $this->seats;
    }
}
