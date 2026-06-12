<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Account;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransferApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $source = new Account('LT-API-SRC', 'EUR', '100.0000');
        $destination = new Account('LT-API-DST', 'EUR', '0.0000');
        $em->persist($source);
        $em->persist($destination);
        $em->flush();
        $this->sourceId = $source->getId()->toRfc4122();
        $this->destinationId = $destination->getId()->toRfc4122();
    }

    private string $sourceId;
    private string $destinationId;

  private function login(): string
    {
        $this->client->request(
            'POST',
            '/api/v1/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => 'paysera_api', 'password' => 'PayseraDemo123!']),
        );

        $payload = json_decode($this->client->getResponse()->getContent(), true);

        return $payload['token'];
    }

    public function testAuthenticationFailure(): void
    {
        $this->client->request('GET', '/api/v1/transfers/00000000-0000-4000-8000-000000000001');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateTransferRequiresIdempotencyKey(): void
    {
        $token = $this->login();
        $this->client->request(
            'POST',
            '/api/v1/transfers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'source_account_id' => $this->sourceId,
                'destination_account_id' => $this->destinationId,
                'amount' => '10.0000',
            ]),
        );

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testSuccessfulTransferViaApi(): void
    {
        $token = $this->login();
        $this->client->request(
            'POST',
            '/api/v1/transfers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_IDEMPOTENCY_KEY' => 'api-success-1',
            ],
            content: json_encode([
                'source_account_id' => $this->sourceId,
                'destination_account_id' => $this->destinationId,
                'amount' => '12.5000',
            ]),
        );

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('COMPLETED', $payload['status']);
        self::assertNotEmpty($this->client->getResponse()->headers->get('X-Request-Id'));
    }

    public function testGetTransferById(): void
    {
        $token = $this->login();
        $this->client->request(
            'POST',
            '/api/v1/transfers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_IDEMPOTENCY_KEY' => 'api-get-1',
            ],
            content: json_encode([
                'source_account_id' => $this->sourceId,
                'destination_account_id' => $this->destinationId,
                'amount' => '1.0000',
            ]),
        );

        $created = json_decode($this->client->getResponse()->getContent(), true);
        $this->client->request(
            'GET',
            '/api/v1/transfers/'.$created['id'],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testInvalidPayloadReturnsValidationError(): void
    {
        $token = $this->login();
        $this->client->request(
            'POST',
            '/api/v1/transfers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_IDEMPOTENCY_KEY' => 'api-invalid',
            ],
            content: json_encode([
                'source_account_id' => 'not-a-uuid',
                'destination_account_id' => $this->destinationId,
                'amount' => '0',
            ]),
        );

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
    }
}
