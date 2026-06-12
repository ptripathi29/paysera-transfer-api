<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateTransferRequest;
use App\Exception\TransferException;
use App\Service\RequestContext;
use App\Service\TransferService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Transfers')]
#[Route('/api/v1')]
final class TransferController extends AbstractController
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
        private readonly RateLimiterFactory $transferApiLimiter,
        private readonly RequestContext $requestContext,
    ) {
    }

    #[Route('/transfers', name: 'transfers_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a fund transfer',
        description: 'Move money from a source account to a destination account. Requires a unique Idempotency-Key header.',
    )]
    #[OA\Parameter(
        name: 'Idempotency-Key',
        in: 'header',
        required: true,
        description: 'Unique client-generated key (prevents duplicate payments on retry)',
        schema: new OA\Schema(type: 'string', example: 'swagger-transfer-001'),
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['source_account_id', 'destination_account_id', 'amount'],
            properties: [
                new OA\Property(
                    property: 'source_account_id',
                    type: 'string',
                    format: 'uuid',
                    example: '019eb2da-68e9-7fca-b370-0e5491470f23',
                ),
                new OA\Property(
                    property: 'destination_account_id',
                    type: 'string',
                    format: 'uuid',
                    example: '019eb2da-68e9-7fca-b370-0e54919d85fb',
                ),
                new OA\Property(property: 'amount', type: 'string', example: '25.5000'),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Transfer completed',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'source_account_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'destination_account_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'amount', type: 'string'),
                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                new OA\Property(property: 'status', type: 'string', example: 'COMPLETED'),
                new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
            ],
        ),
    )]
    #[OA\Response(response: 400, description: 'Missing Idempotency-Key header')]
    #[OA\Response(response: 401, description: 'Missing or invalid JWT')]
    #[OA\Response(response: 422, description: 'Validation failed or insufficient funds')]
    public function create(Request $request): JsonResponse
    {
        $limiter = $this->transferApiLimiter->create($request->getClientIp() ?? 'anonymous');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->error('RATE_LIMIT_EXCEEDED', 'Too many requests.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $idempotencyKey = trim((string) $request->headers->get('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            throw TransferException::missingIdempotencyKey();
        }

        /** @var CreateTransferRequest $dto */
        $dto = $this->serializer->deserialize(
            $request->getContent(),
            CreateTransferRequest::class,
            'json',
        );

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $response = $this->transferService->createTransfer($dto, $idempotencyKey);

        return new JsonResponse($response->toArray(), Response::HTTP_CREATED, [
            'X-Request-Id' => $this->requestContext->getRequestId(),
        ]);
    }

    #[Route('/transfers/{id}', name: 'transfers_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F\-]{36}'])]
    #[OA\Get(summary: 'Get transfer by ID')]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid', example: '019eb7db-2318-7137-a998-f162d18d7617'),
    )]
    #[OA\Response(response: 200, description: 'Transfer details')]
    #[OA\Response(response: 401, description: 'Missing or invalid JWT')]
    #[OA\Response(response: 404, description: 'Transfer not found')]
    public function show(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->error('INVALID_UUID', 'Transfer id must be a valid UUID.', Response::HTTP_BAD_REQUEST);
        }

        $response = $this->transferService->getTransfer(Uuid::fromString($id));

        return new JsonResponse($response->toArray(), Response::HTTP_OK, [
            'X-Request-Id' => $this->requestContext->getRequestId(),
        ]);
    }

    /**
     * @param iterable<\Symfony\Component\Validator\ConstraintViolationInterface> $violations
     */
    private function validationError(iterable $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return new JsonResponse([
            'error' => 'VALIDATION_FAILED',
            'message' => 'Request validation failed.',
            'details' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return JsonResponse
     */
    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => $code,
            'message' => $message,
        ], $status);
    }
}
