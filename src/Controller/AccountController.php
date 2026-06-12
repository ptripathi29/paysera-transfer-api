<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AccountResponse;
use App\Exception\TransferException;
use App\Repository\AccountRepository;
use App\Service\RequestContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly RequestContext $requestContext,
    ) {
    }

    #[Route('/accounts/{id}', name: 'accounts_show', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F\-]{36}'])]
    public function show(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse([
                'error' => 'INVALID_UUID',
                'message' => 'Account id must be a valid UUID.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $account = $this->accountRepository->findByUuid(Uuid::fromString($id));
        if ($account === null) {
            throw TransferException::accountNotFound($id);
        }

        return new JsonResponse(
            AccountResponse::fromEntity($account)->toArray(),
            Response::HTTP_OK,
            ['X-Request-Id' => $this->requestContext->getRequestId()],
        );
    }
}
