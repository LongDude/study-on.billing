<?php

namespace App\Controller\Api\v1;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\ResolveNamedArgumentsPass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use function PHPUnit\Framework\isNull;

#[Route('/api/v1/courses')]
final class CoursesController extends AbstractController
{
    #[Route('', name: 'api_v1_courses', methods: ['GET'])]
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
    public function listActiveCourses(
        #[CurrentUser] $user,
        TransactionRepository $transactionRepository
    ): JsonResponse {
        try {
            $resp = [];
            $activeCourses = $transactionRepository->listActiveCourses($user);
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

    #[Route('/{symbolic_name:course}', name: 'api_v1_course_show', methods: ['GET'])]
    public function show(Course $course): JsonResponse {
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

    #[Route('/{symbolic_name:course}/pay', name: 'api_v1_course_pay', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    public function pay(
        #[CurrentUser] $user,
        Course $course,
        PaymentService $paymentService
    ): JsonResponse {
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
}
