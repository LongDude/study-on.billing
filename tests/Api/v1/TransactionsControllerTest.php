<?php

namespace App\Tests\Api\v1;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransactionsControllerTest extends WebTestCase
{
    private ?KernelBrowser $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    protected function authorizeClient(string $username = "user@email.index", string $password = "user_plain_password"): void
    {
        $this->client
            ->jsonRequest(
                'POST',
                '/api/v1/auth',
                [
                    "username" => $username,
                    "password" => $password,
                ]
            );
        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));
    }

    public function testListRequiresAuthorization(): void
    {
        $this->client->request('GET', '/api/v1/transactions');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListReturnsCurrentUserTransactions(): void
    {
        $this->authorizeClient();

        $this->client->request('GET', '/api/v1/transactions');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(8, $data);
        self::assertNotEmpty(array_filter($data, static fn (array $transaction): bool => (
            $transaction['type'] === 'deposit'
            && $transaction['amount'] === 3000
            && !array_key_exists('course_code', $transaction)
        )));
        self::assertNotEmpty(array_filter($data, static fn (array $transaction): bool => (
            $transaction['type'] === 'payment'
            && $transaction['course_code'] === 'sql-database-design'
            && $transaction['amount'] === 5000
        )));

        foreach ($data as $transaction) {
            self::assertArrayHasKey('id', $transaction);
            self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $transaction['created_at']));
            self::assertContains($transaction['type'], ['payment', 'deposit']);
            self::assertArrayHasKey('amount', $transaction);
        }
    }

    public function testListCanFilterByDepositType(): void
    {
        $this->authorizeClient();

        $this->client->request('GET', '/api/v1/transactions?type=deposit');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(1, $data);
        self::assertSame('deposit', $data[0]['type']);
        self::assertSame(3000, $data[0]['amount']);
        self::assertArrayNotHasKey('course_code', $data[0]);
    }

    public function testListCanFilterByCourseCode(): void
    {
        $this->authorizeClient();

        $this->client->request('GET', '/api/v1/transactions?course_code=symfony-framework-mastery');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(3, $data);
        foreach ($data as $transaction) {
            self::assertSame('payment', $transaction['type']);
            self::assertSame('symfony-framework-mastery', $transaction['course_code']);
            self::assertSame(199.99, $transaction['amount']);
        }
    }

    public function testListCanSkipExpiredRentTransactions(): void
    {
        $this->authorizeClient();

        $this->client->request('GET', '/api/v1/transactions?course_code=symfony-framework-mastery&skip_expired=true');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(1, $data);
        self::assertSame('payment', $data[0]['type']);
        self::assertSame('symfony-framework-mastery', $data[0]['course_code']);
    }

    public function testDepositRequiresAuthorization(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/transactions/deposit', ['deposit' => 100]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testDepositAddsMoneyToCurrentUserBalance(): void
    {
        $this->authorizeClient();

        $this->client->jsonRequest('POST', '/api/v1/transactions/deposit', ['deposit' => 123.45]);

        self::assertResponseIsSuccessful();
        self::assertSame('Success', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/v1/users/current');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        self::assertEqualsWithDelta(3123.45, $data['balance'], 1e-9);
    }

    public function testDepositRejectsNonPositiveAmount(): void
    {
        $this->authorizeClient();

        $this->client->jsonRequest('POST', '/api/v1/transactions/deposit', ['deposit' => 0]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('Неверная сумма пополнения', $this->client->getResponse()->getContent());
    }

    private function getJsonResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
