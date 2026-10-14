<?php

namespace App\Controller;

use App\Contracts\Services\ExcursionServiceInterface;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use App\Http\Views\ExcursionListView;
use App\Http\Views\ExcursionView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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
     * @return JsonResponse The created excursion, with HTTP status 201.
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
    public function create(#[MapRequestPayload] CreateExcursionRequest $request): JsonResponse
    {
        $this->logger->debug("Creating new excursion.");

        $excursion = $this->excursionService->createExcursion($request);

        $this->logger->debug("Excursion created.", ['excursion_id' => $excursion->getId()]);

        return $this->json(new ExcursionView($excursion), Response::HTTP_CREATED);
    }

    #[Route('', name: 'get-excursions', methods: ['GET'])]
    public function search(
        #[MapRequestPayload] SearchExcursionRequest $request = new SearchExcursionRequest()
    ): JsonResponse
    {
        $excursions = $this->excursionService->searchExcursions($request);

        return $this->json(new ExcursionListView($excursions), Response::HTTP_OK);
    }
}
