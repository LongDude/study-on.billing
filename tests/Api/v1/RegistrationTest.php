<?php

namespace App\Tests\Api\v1;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationTest extends WebTestCase
{
    private ?JWTTokenManagerInterface $JwtManager;

    private ?KernelBrowser $client;

    public function setUp(): void {
        parent::setUp();
        $this->client = static::createClient();
        $this->JwtManager = $this->client->getContainer()->get('lexik_jwt_authentication.jwt_manager');
    }

    protected function authorizeClient(string $username = "user@email.index", string $password = "user_plain_password"): void{
        $this->client
            ->jsonRequest(
                'POST',
                '/api/v1/auth',
                [
                    "username" => $username,
                    "password" => $password
                ]
            );
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), True);
        $this->client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));
    }

    public function testSuccessfullUserRegistration(): void
    {
        // Create user
        $this->client->jsonRequest(
            'POST',
            '/api/v1/register',
            [
                "email" => "newuser@email.inbox",
                "password" => "newuser_password",
            ]
        );
        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(201);

        // Test user register returned correct login token
        $data = json_decode($this->client->getResponse()->getContent(), True);
        self::assertArrayHasKey('token', $data);
        $token = $data["token"];
        $token_payload = $this->JwtManager->parse($token);
        self::assertArrayHasKey("username", $token_payload);
        self::assertSame("newuser@email.inbox", $token_payload["username"]);

        // Try to authorize with token to new user
        $this->client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));
        $this->client
            ->request(
                'GET',
                '/api/v1/users/current'
            );
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), True);
        self::assertArrayHasKey("username", $data);
        self::assertSame("newuser@email.inbox", $data["username"]);
    }

    public function testEmailFormatConstraint(): void{
        $this->client->jsonRequest(
            'POST',
            '/api/v1/register',
            [
                "email" => "user@email",
                "password" => "password", // correct password
            ]
        );
        self::assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), True);
        self::assertArrayHasKey("errors", $data);
        self::assertArrayHasKey("email", $data["errors"]);
        self::assertGreaterThan(1, $data['errors']["email"]);
    }

    public function testMinSizeConstraints(): void{
        $this->client->jsonRequest(
            'POST',
            '/api/v1/register',
            [
                "email" => "newuser@email.inbox", // Correct email (no min constraint)
                "password" => "pass",
            ]
        );
        self::assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), True);
        self::assertArrayHasKey("errors", $data);
        self::assertArrayHasKey("password", $data["errors"]);
        self::assertGreaterThan(1, $data['errors']["password"]);
    }

    public function testMaxSizeConstraints(): void{
        $this->client->jsonRequest(
            'POST',
            '/api/v1/register',
            [
                "email" => "newuser" . implode('a', range(0, 255)) . "@email.inbox", // Correct email
                "password" => "pass" . implode('a', range(0, 255)),
            ]
        );
        self::assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), True);
        self::assertArrayHasKey("errors", $data);
        self::assertArrayHasKey("email", $data["errors"]);
        self::assertGreaterThan(1, $data['errors']["email"]);
        self::assertArrayHasKey("password", $data["errors"]);
        self::assertGreaterThan(1, $data['errors']["password"]);
    }

    public function testNotEmptyConstraint(): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/v1/register',
            [
                "email" => "",
                "password" => "",
            ]
        );
        self::assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), True);
        self::assertArrayHasKey("errors", $data);

        self::assertArrayHasKey("email", $data["errors"]);
        self::assertGreaterThan(2, $data['errors']["email"]);
        self::assertArrayHasKey("password", $data["errors"]);
        self::assertGreaterThan(2, $data['errors']["password"]);
    }

    public function testEmailUniqueConstraint(): void{
        $this->client->jsonRequest(
            'POST',
            '/api/v1/register',
            [
                "email" => "user@email.index",
                "password" => "password", // correct password
            ]
        );
        self::assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), True);
        self::assertArrayHasKey("errors", $data);
        self::assertArrayHasKey("email", $data["errors"]);
        self::assertGreaterThan(1, $data['errors']["email"]);
    }
}
