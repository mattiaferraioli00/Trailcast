<?php

namespace App\Controller;

use App\Attributes\ResponseStatus;
use App\Contracts\Services\ExcursionServiceInterface;
use App\Entity\Excursion;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\NotEnoughSeatsException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\BookingExcursionRequest;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use App\Http\Resources\BookingView;
use App\Http\Resources\ExcursionList\ExcursionListView;
use App\Http\Resources\ExcursionView;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/excursions')]
#[OA\Tag(name: 'Excursions')]
class ExcursionController extends AbstractController
{

    public function __construct(
        protected readonly ExcursionServiceInterface $excursionService,
        protected readonly LoggerInterface $logger
    )
    {
    }

    /**
     * Creates a new excursion. Staff only.
     *
     * The city is resolved to coordinates through the geocoding provider before the excursion is stored.
     * The 201 status is set by {@see ResponseStatus}.
     *
     * @param CreateExcursionRequest $request Payload mapped and validated by {@see MapRequestPayload}.
     *
     * @return ExcursionView The created excursion.
     *
     * @throws LocationNotFoundException     When no location matches the city in the given country (404).
     * @throws GeocodingUnavailableException When the geocoding provider cannot be reached (503).
     */
    #[Route('', name: 'create-excursion', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    #[ResponseStatus(Response::HTTP_CREATED)]
    #[Security(name: 'Bearer')]
    #[OA\Response(
        response: 201,
        description: 'Excursion created.',
        content: new Model(type: ExcursionView::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Missing or invalid token.',
    )]
    #[OA\Response(
        response: 403,
        description: 'The authenticated user is not staff.',
    )]
    #[OA\Response(
        response: 404,
        description: 'No location matches the city in the given country.',
    )]
    #[OA\Response(
        response: 422,
        description: 'Invalid payload.',
    )]
    #[OA\Response(
        response: 503,
        description: 'Geocoding provider unavailable. Retry after the seconds in the Retry-After header.',
    )]
    public function create(#[MapRequestPayload] CreateExcursionRequest $request): ExcursionView
    {
        $this->logger->debug('Creating excursion.', [
            'city' => $request->getCity(),
            'country' => $request->getCountry(),
            'date' => $request->getDate()->format('Y-m-d'),
            'capacity' => $request->getCapacity(),
        ]);

        $excursion = $this->excursionService->createExcursion($request);

        $this->logger->debug('Excursion created.', ['excursion_id' => $excursion->getId()]);

        return new ExcursionView($excursion);
    }

    /**
     * Lists the bookable excursions (date from today on, at least one seat available), each with the
     * weather forecast of its day.
     *
     * The forecast is always present: when it cannot be provided, its status says why
     * (not_yet_available, unavailable), so a weather provider failure never fails the request.
     *
     * @param SearchExcursionRequest $request Optional filters (city, country, date range, seats) and pagination,
     *                                        mapped from the query string and validated by {@see MapQueryString}.
     *
     * @return ExcursionListView A page of excursions with their forecast.
     *
     * @throws InvalidArgumentException When a forecast cache key is invalid.
     */
    #[Route('', name: 'get-excursions', methods: ['GET'])]
    #[ResponseStatus(Response::HTTP_OK)]
    #[OA\Response(
        response: 200,
        description: 'Page of bookable excursions, each with the forecast of its day.',
        content: new Model(type: ExcursionListView::class)
    )]
    #[OA\Response(
        response: 422,
        description: 'Invalid query parameters.',
    )]
    public function search(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] SearchExcursionRequest $request = new SearchExcursionRequest()
    ): ExcursionListView
    {
        $this->logger->debug('Searching excursions.', array_filter([
            'city' => $request->getCity(),
            'country' => $request->getCountry(),
            'date_from' => $request->getDateFrom()?->format('Y-m-d'),
            'date_to' => $request->getDateTo()?->format('Y-m-d'),
            'seats' => $request->getSeats(),
            'page' => $request->getPage(),
            'per_page' => $request->getPerPage(),
        ], fn (mixed $value): bool => $value !== null));

        $excursions = $this->excursionService->searchExcursions($request);

        $this->logger->debug('Excursions found.', [
            'returned' => count($excursions->getItems()),
            'total' => $excursions->getTotal(),
        ]);

        return new ExcursionListView($excursions);
    }

    /**
     * Books seats on an excursion for a customer.
     *
     * The excursion is resolved from the {id} route parameter, so a missing excursion results in a 404
     * before this method runs. Concurrent bookings on the same excursion are serialised by the service,
     * so an excursion never sells more seats than its capacity. The 201 status is set by {@see ResponseStatus}.
     *
     * @param Excursion               $excursion Excursion resolved from the route.
     * @param BookingExcursionRequest $request   Payload mapped and validated by {@see MapRequestPayload}.
     *
     * @return BookingView The created booking.
     *
     * @throws NotEnoughSeatsException|LockWaitTimeoutException When fewer seats are left than requested (409).
     */
    #[Route('/{id}/bookings', name: 'book-excursion', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[ResponseStatus(Response::HTTP_CREATED)]
    #[OA\Response(
        response: 201,
        description: 'Booking created. Returns the booking id, the excursion id and the number of seats booked.',
        content: new Model(type: BookingView::class)
    )]
    #[OA\Response(
        response: 404,
        description: 'The excursion does not exist.',
    )]
    #[OA\Response(
        response: 409,
        description: 'Not enough seats left to fulfil the booking.',
    )]
    #[OA\Response(
        response: 422,
        description: 'Invalid payload.',
    )]
    public function book(
        Excursion $excursion,
        #[MapRequestPayload] BookingExcursionRequest $request
    ): BookingView
    {
        $this->logger->debug('Booking excursion.', [
            'excursion_id' => $excursion->getId(),
            'seats' => $request->getSeats(),
        ]);

        $booking = $this->excursionService->bookExcursion($excursion, $request);

        $this->logger->debug('Excursion booked.', [
            'booking_id' => $booking->getId(),
            'excursion_id' => $excursion->getId(),
            'seats' => $booking->getSeats(),
        ]);

        return new BookingView($booking);
    }
}
