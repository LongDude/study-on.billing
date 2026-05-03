<?php

namespace App\Controller\Api\v1;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
            return $this->json([
                "success" => true,
                "course_type" => match($course->getPrice()) {
                    0 => "free",
                    1 => "rent",
                    2 => "buy",
                },
                "expires_at" => date_format($trans->getValidUntil(), "Y-m-d H:i:s"),
            ]);
        } catch (Exception $exception) {
            return $this->json(
                [
                    'message' => "Сервис временно недоступен"
                ],
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/deposit', name: 'api_v1_deposit', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    public function deposit(
        #[CurrentUser] $user,
        Request $request,
        PaymentService $paymentService,
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            if (!isset($data["deposit"]) || $data["deposit"] <= 0) {
                return new JsonResponse($this->json(["error" => "Неверная сумма пополнения"]), Response::HTTP_BAD_REQUEST);
            }
        } catch (Exception $exception) {
            return new JsonResponse("",Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentService->deposit($user, (float) $data["deposit"]);
        } catch (Exception $exception) {
            return new JsonResponse($this->json(["error" => "Сервис временно недоступен"]),Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }
}
