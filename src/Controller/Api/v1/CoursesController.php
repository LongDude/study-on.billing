<?php

namespace App\Controller\Api\v1;

use App\DTO\CoursePostDTO;
use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/courses')]
final class CoursesController extends AbstractController
{
    #[Route('', name: 'api_v1_courses', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses',
        description: 'Returns all available courses with their purchase type and price for paid courses.',
        summary: 'List courses',
        security: [],
        tags: ['Courses'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Courses list',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'code', description: 'Course symbolic code', type: 'string', example: 'sql-database-design'),
                            new OA\Property(property: 'type', description: 'Course access type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'buy'),
                            new OA\Property(property: 'price', description: 'Course price. Returned only for rent and buy courses.', type: 'number', format: 'float', example: 5000),
                        ],
                        type: 'object',
                    ),
                ),
            ),
        ],
    )]
    public function index(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findAll();
        $resp = [];
        foreach ($courses as $course) {
            $new_row = [];
            $new_row['code'] = $course->getSymbolicName();
            $new_row['type'] = match ($course->getCourseType()) {
                0 => "free",
                1 => "rent",
                2 => "buy"
            };
            if ($new_row['type'] !== "free") {
                $new_row['price'] = $course->getPrice();
            }
            $resp[] = $new_row;
        }

        return $this->json($resp);
    }

    #[Route('/active', name: 'api_v1_courses_active', methods: ['GET'])]
    #[IsGranted("ROLE_USER")]
    #[OA\Get(
        path: '/api/v1/courses/active',
        description: 'Returns active paid courses for the current user. Permanent access courses do not contain valid_until.',
        summary: 'List active courses',
        security: [['Bearer' => []]],
        tags: ['Courses'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Active courses list',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'code', description: 'Course symbolic code', type: 'string', example: 'sql-database-design'),
                            new OA\Property(property: 'valid_until', description: 'ISO 8601 access expiration date for rented courses', type: 'string', format: 'date-time', example: '2026-05-12T15:30:00+03:00'),
                        ],
                        type: 'object',
                    ),
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
            ),
            new OA\Response(
                response: 500,
                description: 'Service temporarily unavailable',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Сервис временно недоступен'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function checkActiveCourses(
        #[CurrentUser] $user,
        Request $request,
        TransactionRepository $transactionRepository,
        CourseRepository $courseRepository,
    ): JsonResponse {
        $targetCode=$request->query->get('code');

        try {
            $resp = [];
            $activeCourses = $transactionRepository->listActiveCourses($user, $targetCode);
            foreach ($activeCourses as $course) {
                if (is_null($course['valid_until'])) {
                    unset($course['valid_until']);
                } else {
                    $course['valid_until'] = $course['valid_until']->format('c');
                }
                $resp[] = $course;
            }
            return $this->json($resp);
        } catch (Exception $exception) {
            return $this->json([
                'message' => "Сервис временно недоступен"
            ],
                status: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{symbolic_name}', name: 'api_v1_course_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses/{symbolic_name}',
        description: 'Returns information about one course by its symbolic code.',
        summary: 'Get course',
        security: [],
        tags: ['Courses'],
        parameters: [
            new OA\Parameter(
                name: 'symbolic_name',
                description: 'Course symbolic code',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'sql-database-design',
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Course information',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', description: 'Course symbolic code', type: 'string', example: 'sql-database-design'),
                        new OA\Property(property: 'type', description: 'Course access type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'buy'),
                        new OA\Property(property: 'price', description: 'Course price. Returned only for rent and buy courses.', type: 'number', format: 'float', example: 5000),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Course not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Курс не найден'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function show(string $symbolic_name, CourseRepository $courseRepository): JsonResponse {
        $course = $courseRepository->findOneBy(['symbolic_name' => $symbolic_name]);
        if (null === $course) {
            return $this->json([
                'message' => 'Курс не найден',
            ], Response::HTTP_NOT_FOUND);
        }

        $resp = [];
        $resp['code'] = $course->getSymbolicName();
        $resp['type'] = match ($course->getCourseType()) {
            0 => "free",
            1 => "rent",
            2 => "buy"
        };
        if ($resp['type'] !== "free") {
            $resp['price'] = $course->getPrice();
        }
        return $this->json($resp);
    }

    #[Route('/{symbolic_name}/pay', name: 'api_v1_course_pay', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    #[OA\Post(
        path: '/api/v1/courses/{symbolic_name}/pay',
        description: 'Pays for course access using the current user balance.',
        summary: 'Pay for course',
        security: [['Bearer' => []]],
        tags: ['Courses'],
        parameters: [
            new OA\Parameter(
                name: 'symbolic_name',
                description: 'Course symbolic code',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'sql-database-design',
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Course successfully paid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'course_type', description: 'Paid course access type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                        new OA\Property(property: 'expires_at', description: 'ISO 8601 access expiration date for rented courses', type: 'string', format: 'date-time', example: '2026-05-12T15:30:00+03:00'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
            ),
            new OA\Response(
                response: 404,
                description: 'Course not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Курс не найден'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 406,
                description: 'Insufficient funds',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 406),
                        new OA\Property(property: 'message', type: 'string', example: 'На вашем счету недостаточно средств'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 500,
                description: 'Service temporarily unavailable',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Сервис временно недоступен'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function pay(
        #[CurrentUser] $user,
        string $symbolic_name,
        CourseRepository $courseRepository,
        PaymentService $paymentService
    ): JsonResponse {
        $course = $courseRepository->findOneBy(['symbolic_name' => $symbolic_name]);
        if (null === $course) {
            return $this->json([
                'message' => 'Курс не найден',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $trans = $paymentService->payment($user, $course);
            if (null === $trans) {
                return $this->json([
                        "code" => Response::HTTP_NOT_ACCEPTABLE,
                        "message" => "На вашем счету недостаточно средств",
                    ],
                    status: Response::HTTP_NOT_ACCEPTABLE,
                );
            }
            $paymentResponse = [
                "success" => true,
                "course_type" => match($course->getCourseType()) {
                    0 => "free",
                    1 => "rent",
                    2 => "buy",
                },
            ];
            if ($trans->getValidUntil()){
                $paymentResponse["expires_at"] = date_format($trans->getValidUntil(), "c");
            }
            return $this->json($paymentResponse);
        } catch (Exception $exception) {
            return $this->json(
                [
                    'message' => "Сервис временно недоступен"
                ],
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route("", name: "api_courses_create", methods: ["POST"])]
    #[IsGranted("ROLE_SUPER_ADMIN")]
    public function create(
        Request $request,
        ValidatorInterface $validator,
        CourseRepository $courseRepository,
        Serializer  $serializer,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $courseDto = $serializer->deserialize($request->getContent(), CoursePostDTO::class, 'json');
        $errors = $validator->validate($courseDto);

        if ($errors->count() > 0) {
            $errs = array();
            foreach ($errors as $brokenConstraint) {
                $errs[$brokenConstraint->getPropertyPath()] = $brokenConstraint->getMessage();
            }
            return $this->json(["errors" => $errs], Response::HTTP_BAD_REQUEST);
        }

        try {
            $course = new Course()
                ->setTitle($courseDto->title)
                ->setSymbolicName($courseDto->code)
                ->setPrice($courseDto->price);

            switch ($courseDto->type) {
                case "free":
                    $course->setCourseType(0);
                    break;
                case "rent":
                    $course->setCourseType(1);
                    break;
                case "buy":
                    $course->setCourseType(2);
                    break;
            }

            $entityManager->persist($course);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
                return $this->json(["errors" => [
                    "symbolic_name" => "Course with same code already exists",
                ]],                Response::HTTP_BAD_REQUEST);
        } catch (ORMException) {
            return $this->json(["errors" => ["message" => "Server error"]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(["success" => true], Response::HTTP_CREATED);
    }

    #[Route("/{code}", name: "api_courses_create", methods: ["POST"])]
    #[IsGranted("ROLE_SUPER_ADMIN")]
    public function update(
        Request $request,
        ValidatorInterface $validator,
        CourseRepository $courseRepository,
        EntityManagerInterface $entityManager,
        Serializer  $serializer,
        string $code,
    ): JsonResponse {
        $courseDto = $serializer->deserialize($request->getContent(), CoursePostDTO::class, 'json');
        $errors = $validator->validate($courseDto);

        if ($errors->count() > 0) {
            $errs = array();
            foreach ($errors as $brokenConstraint) {
                $errs[$brokenConstraint->getPropertyPath()] = $brokenConstraint->getMessage();
            }
            return $this->json(["errors" => $errs], Response::HTTP_BAD_REQUEST);
        }

        try {
            $course = $courseRepository->findOneBy(['symbolic_name' => $code]);
            if (null === $course) {
                return $this->json(["errors" => ["course" => "course not found"]], Response::HTTP_NOT_FOUND);
            }

            $course
                ->setTitle($courseDto->title)
                ->setSymbolicName($courseDto->code)
                ->setPrice($courseDto->price);

            switch ($courseDto->type) {
                case "free":
                    $course->setCourseType(0);
                    break;
                case "rent":
                    $course->setCourseType(1);
                    break;
                case "buy":
                    $course->setCourseType(2);
                    break;
            }

            $entityManager->persist($course);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            return $this->json(["errors" => [
                "symbolic_name" => "Course with same code already exists",
            ]],                Response::HTTP_BAD_REQUEST);
        } catch (ORMException) {
            return $this->json(["errors" => ["message" => "Server error"]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(["success" => true], Response::HTTP_OK);
    }
}
