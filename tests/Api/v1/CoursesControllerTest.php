<?php

namespace App\Tests\Api\v1;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CoursesControllerTest extends WebTestCase
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
                    "password" => $password
                ]
            );
        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));
    }

    public function testIndexReturnsAllCoursesForAnonymousUser(): void
    {
        $this->client->request('GET', '/api/v1/courses');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(5, $data);
        self::assertContains([
            "code" => "web-development-basics",
            "type" => "free",
        ], $data);
        self::assertContains([
            "code" => "sql-database-design",
            "type" => "buy",
            "price" => 5000,
        ], $data);
    }

    public function testShowReturnsCourseInfo(): void
    {
        $this->client->request('GET', '/api/v1/courses/symfony-framework-mastery');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'code' => 'symfony-framework-mastery',
            'type' => 'rent',
            'price' => 199.99,
        ], $this->getJsonResponse());
    }

    public function testShowReturnsNotFoundForUnknownCourse(): void
    {
        $this->client->request('GET', '/api/v1/courses/unknown-course');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['message' => 'Курс не найден'], $this->getJsonResponse());
    }

    public function testActiveCoursesRequireAuthorization(): void
    {
        $this->client->request('GET', '/api/v1/courses/active');

        self::assertContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testActiveCoursesReturnsOnlyCurrentUserActiveAccess(): void
    {
        $this->authorizeClient();

        $this->client->request('GET', '/api/v1/courses/active');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertCount(5, $data);
        self::assertSame(
            [
                'docker-for-developers',
                'python-for-data-science',
                'sql-database-design',
                'symfony-framework-mastery',
                'web-development-basics',
            ],
            $this->sortedColumn($data, 'code'),
        );

        $rentCourse = $this->findCourseByCode($data, 'symfony-framework-mastery');
        self::assertArrayHasKey('valid_until', $rentCourse);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $rentCourse['valid_until']));

        $buyCourse = $this->findCourseByCode($data, 'sql-database-design');
        self::assertArrayNotHasKey('valid_until', $buyCourse);
    }

    public function testPayRequiresAuthorization(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/courses/symfony-framework-mastery/pay');

        self::assertContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testPayRentCourseCreatesLimitedAccess(): void
    {
        $this->authorizeClient();

        $this->client->jsonRequest('POST', '/api/v1/courses/symfony-framework-mastery/pay');

        self::assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        self::assertTrue($data['success']);
        self::assertSame('rent', $data['course_type']);
        self::assertArrayHasKey('expires_at', $data);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['expires_at']));
    }

    public function testPayBuyCourseReturnsInsufficientFunds(): void
    {
        $this->authorizeClient();

        $this->client->jsonRequest('POST', '/api/v1/courses/sql-database-design/pay');

        self::assertResponseStatusCodeSame(406);
        self::assertSame([
            'code' => 406,
            'message' => 'На вашем счету недостаточно средств',
        ], $this->getJsonResponse());
    }

    public function testPayReturnsNotFoundForUnknownCourse(): void
    {
        $this->authorizeClient();

        $this->client->jsonRequest('POST', '/api/v1/courses/unknown-course/pay');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['message' => 'Курс не найден'], $this->getJsonResponse());
    }

    private function getJsonResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function sortedColumn(array $rows, string $column): array
    {
        $values = array_column($rows, $column);
        sort($values);

        return $values;
    }

    private function findCourseByCode(array $courses, string $code): array
    {
        foreach ($courses as $course) {
            if ($course['code'] === $code) {
                return $course;
            }
        }

        self::fail(sprintf('Course "%s" was not found in response.', $code));
    }
}
