<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\LoginRequest;
use App\Security\ApiUserProvider;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Auth')]
#[Route('/api/v1')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly ApiUserProvider $userProvider,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Obtain JWT access token',
        description: 'Authenticate with demo credentials and receive a Bearer token for protected endpoints.',
        security: [],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username', 'password'],
            properties: [
                new OA\Property(property: 'username', type: 'string', example: 'paysera_api'),
                new OA\Property(property: 'password', type: 'string', example: 'PayseraDemo123!'),
            ],
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'JWT token issued',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
            ],
        ),
    )]
    #[OA\Response(response: 401, description: 'Invalid credentials')]
    public function login(Request $request): JsonResponse
    {
        /** @var LoginRequest $dto */
        $dto = $this->serializer->deserialize(
            $request->getContent(),
            LoginRequest::class,
            'json',
        );

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
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

        if (!$this->userProvider->validateCredentials($dto->username ?? '', $dto->password ?? '')) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Invalid credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->userProvider->loadUserByIdentifier($dto->username ?? '');

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
        ]);
    }
}
