<?php

namespace App\Tests\Api\v1;

use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthorizationTest extends WebTestCase
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

    public function testSuccessfullUserLogin(): void
    {
        $this->client
            ->jsonRequest(
                'POST',
                '/api/v1/auth',
                [
                    "username" => "user@email.index",
                    "password" => "user_plain_password"
                ]
            );
        self::assertResponseIsSuccessful();

        // Try extracting response
        try {
            $resp_json = json_decode($this->client->getResponse()->getContent(), True, 2, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::fail("Bad response:".$e->getMessage());
        }

        // Try extracting token
        self::assertArrayHasKey("token", $resp_json);
        $token = $resp_json["token"];
        // Try decoding token
        $token_payload = $this->JwtManager->parse($token);

        self::assertArrayHasKey("username", $token_payload);
        self::assertSame("user@email.index", $token_payload["username"]);
    }

    public function testNotSuccessfulUserLogin(): void
    {
        $this->client
            ->jsonRequest(
                'POST',
                '/api/v1/auth',
                [
                    "username" => "user1@email.index",
                    "password" => "user_plain_password"
                ]
            );
        self::assertResponseStatusCodeSame(401, false);
    }

    public function testTokenValid(): void {
        $this->authorizeClient("user@email.index", "user_plain_password");
        $this->client
            ->request(
                'GET',
                '/api/v1/users/current'
            );
        self::assertResponseIsSuccessful();

        try {
            $data = json_decode($this->client->getResponse()->getContent(), True, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::fail("Bad response:".$e->getMessage());
        }
        self::assertArrayHasKey("username", $data);
        self::assertSame("user@email.index", $data["username"]);
        self::assertArrayHasKey("roles", $data);
        self::assertCount(1, $data['roles']);
        self::assertContains("ROLE_USER", $data['roles']);
        self::assertArrayHasKey("balance", $data);        try {
            $data = json_decode($this->client->getResponse()->getContent(), True, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::fail("Bad response:".$e->getMessage());
        }
        self::assertArrayHasKey("username", $data);
        self::assertSame("user@email.index", $data["username"]);

        self::assertEquals("17.2", $data['balance']);
    }

    public function testUnauthorizedUserLogin(): void {
        $this->client
            ->request(
                'GET',
                '/api/v1/users/current'
            );
        self::assertResponseStatusCodeSame(401);
    }
}
