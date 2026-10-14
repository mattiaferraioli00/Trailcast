<?php

namespace App\Controller;

use App\Contracts\Services\ExcursionServiceInterface;
use App\Exceptions\GeocodingUnavailableException;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Views\ExcursionView;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/excursions')]
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
    public function create(#[MapRequestPayload] CreateExcursionRequest $request): JsonResponse
    {
        $this->logger->debug("Creating new excursion.");

        $excursion = $this->excursionService->createExcursion($request);

        $this->logger->debug("Excursion created.", ['excursion_id' => $excursion->getId()]);

        return $this->json(new ExcursionView($excursion), Response::HTTP_CREATED);
    }
}
