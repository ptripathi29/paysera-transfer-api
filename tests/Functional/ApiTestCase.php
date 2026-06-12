<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected ?string $token = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function authenticate(string $username = 'paysera_api', string $password = 'PayseraDemo123!'): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR),
        );

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->token = $payload['token'] ?? null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function jsonRequest(string $method, string $uri, array $payload = [], array $headers = []): void
    {
        $defaultHeaders = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
        ];

        $this->client->request(
            $method,
            $uri,
            server: array_merge($defaultHeaders, $headers),
            content: $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
