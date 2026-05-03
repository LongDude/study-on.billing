<?php

namespace App\Tests\Api\v1;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class
CoursesControllerTest extends WebTestCase
{
    private ?KernelBrowser $client;

    public function setUp(): void {
        parent::setUp();
        $this->client = static::createClient();
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
    public function testIndex(): void
    {
        $this->authorizeClient("user@email.index", "user_plain_password");
        $this->client->request('GET', '/api/v1/courses');
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), True);
        self::assertCount(5, $data);
        self::assertContains([
		"code" => "sql-database-design",
		"type" => "buy",
		"price" => 5000,
            ], $data);
    }
}
