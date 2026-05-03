<?php

namespace App\Controller\Api\v1;

use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/transactions')]
final class TransactionsController extends AbstractController
{
    #[IsGranted("ROLE_USER")]
    #[Route('', name: 'api_v1_transactions', methods: ['GET'])]
    public function listTransactions(
        #[MapQueryParameter("type")] ?string $type,
        #[MapQueryParameter("course_code")] ?string $courseCode,
        #[MapQueryParameter("skip_expired")] ?bool $skipExpired,
        TransactionRepository $transactionRepository,
    ): JsonResponse
    {
        $transactions = $transactionRepository->listFiltered($type, $courseCode, $skipExpired);
        return $this->json(array_map(static function($transaction) {
            $transaction["type"] = match ($transaction["type"]) {0 => "payment", 1 => "deposit"};
            $transaction["created_at"] = $transaction["created_at"]->format('c');
            if ("deposit" === $transaction["type"]){
                unset($transaction["course_code"]);
            }
            return $transaction;
        }, $transactions));
    }

    #[Route('/deposit', name: 'api_v1_deposit', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    public function deposit(
        #[CurrentUser] $user,
        Request $request,
        PaymentService $paymentService,
    ): Response {
        try {
            $data = json_decode($request->getContent(), true);
            if (!isset($data["deposit"]) || $data["deposit"] <= 0) {
                return new Response("Неверная сумма пополнения", Response::HTTP_BAD_REQUEST);
            }
        } catch (Exception $exception) {
            return new Response("",Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentService->deposit($user, (float) $data["deposit"]);
            return new Response("Success", Response::HTTP_OK);
        } catch (Exception $exception) {
            return new Response($this->json(["error" => "Сервис временно недоступен"]),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
