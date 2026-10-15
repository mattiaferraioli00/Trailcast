<?php

namespace App\Http\Requests;

use Symfony\Component\Validator\Constraints as Assert;

class BookingExcursionRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $customer_name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Email]
    public string $customer_email;

    #[Assert\GreaterThan(0)]
    public int $seats;

    public function __construct(
        string $customer_name,
        string $customer_email,
        int $seats
    )
    {
        $this->customer_name = $customer_name;
        $this->customer_email = $customer_email;
        $this->seats = $seats;
    }

    /**
     * @return string Name of the customer making the booking (not blank, max 255 characters).
     */
    public function getCustomerName(): string
    {
        return $this->customer_name;
    }

    /**
     * @return string Email of the customer making the booking (valid address, max 255 characters).
     */
    public function getCustomerEmail(): string
    {
        return $this->customer_email;
    }

    /**
     * @return int Number of seats to book (at least 1).
     */
    public function getSeats(): int
    {
        return $this->seats;
    }
}
