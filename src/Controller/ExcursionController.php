<?php

namespace App\Controller;

use App\Contracts\Services\BookingServiceInterface;
use App\Contracts\Services\ExcursionServiceInterface;
use App\Entity\Excursion;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\BookingExcursionRequest;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use App\Http\Resources\BookingView;
use App\Http\Resources\ExcursionListView;
use App\Http\Resources\ExcursionView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/excursions')]
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
     * Creates a new excursion from the request payload.
     *
     * @param CreateExcursionRequest $request Payload mapped and validated by {@see MapRequestPayload}.
     * @return ExcursionView The created excursion, with HTTP status 201.
     * @throws GeocodingUnavailableException
     */
    #[Route('', name: "create-excursion", methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Excursion created.',
        content: new Model(type: ExcursionView::class)
    )]
    #[OA\Response(
        response: 503,
        description: 'Open-Meteo geocoding is unavailable.',
    )]
    #[OA\Response(
        response: 404,
        description: 'Nessuna località trovata.',
    )]
    #[OA\Response(
        response: 422,
        description: 'Unprocessable content.',
    )]
    public function create(#[MapRequestPayload] CreateExcursionRequest $request): ExcursionView
    {
        $this->logger->debug("Creating new excursion.");

        $excursion = $this->excursionService->createExcursion($request);

        $this->logger->debug("Excursion created.", ['excursion_id' => $excursion->getId()]);

        return new ExcursionView($excursion);
    }

    /**
     * Searches the bookable excursions (future date, available capacity) and attaches the weather forecast of
     * the excursion day, when available.
     *
     * @param SearchExcursionRequest $request Optional filters (city, country, date range, seats) and pagination,
     *                                        mapped from the query string and validated by {@see MapQueryString}.
     * @return ExcursionListView A page of excursions with their forecast, with HTTP status 200.
     * @throws InvalidArgumentException
     */
    #[Route('', name: 'get-excursions', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Page of bookable excursions with the forecast of the excursion day, when available.',
        content: new Model(type: ExcursionListView::class)
    )]
    #[OA\Response(
        response: 422,
        description: 'Unprocessable content.',
    )]
    public function search(
        #[MapQueryString] SearchExcursionRequest $request = new SearchExcursionRequest()
    ): ExcursionListView
    {
        $this->logger->debug('Searching excursions.', [
            'city' => $request->getCity(),
            'country' => $request->getCountry(),
            'date_from' => $request->getDateFrom()?->format('Y-m-d'),
            'date_to' => $request->getDateTo()?->format('Y-m-d'),
            'seats' => $request->getSeats(),
            'page' => $request->getPage(),
            'per_page' => $request->getPerPage(),
        ]);

        $excursions = $this->excursionService->searchExcursions($request);

        $this->logger->debug('Excursions found.', [
            'returned' => count($excursions->getItems()),
            'total' => $excursions->getTotal(),
        ]);

        return new ExcursionListView($excursions);
    }

    #[Route('/{id}/bookings', name: 'book-excursion', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Booking created successfully. Returns the booking ID, excursion ID, and number of seats reserved.',
        content: new Model(type: BookingView::class)

    )]
    #[OA\Response(
        response: 409,
        description: 'Insufficient seats available on the excursion to fulfill the booking request.',
    )]
    #[OA\Response(
        response: 404,
        description: 'The requested excursion was not found.',
    )]
    public function book(
        Excursion $excursion,
        #[MapRequestPayload] BookingExcursionRequest $request
    )
    {
        $booking = $this->excursionService->bookExcursion($excursion, $request);

        return new BookingView($booking);
    }
}
