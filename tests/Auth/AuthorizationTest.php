<?php

namespace App\Tests\Auth;

use Lcobucci\JWT\Decoder;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AuthorizationTest extends WebTestCase
{
    private ?JWTTokenManagerInterface $JwtManager;
    private ?KernelBrowser $client;

    public function setUp(): void {
        parent::setUp();
        $this->client = static::createClient();
        $this->JwtManager = $this->client->getContainer()->get('lexik_jwt_authentication.jwt_manager');
    }

    public function testSuccessfullUserLogin(): void
    {
        $this->client
            ->request(
                'POST',
                '/api/v1/auth',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                ],
                content: json_encode([
                    "username" => "user@email.index",
                    "password" => "user_plain_password"
                ])
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
            ->request(
                'POST',
                '/api/v1/auth',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                ],
                content: json_encode([
                    "username" => "user1@email.index",
                    "password" => "user_plain_password"
                ])
            );
        self::assertResponseStatusCodeSame(401, false);
    }
}
