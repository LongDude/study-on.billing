<?php

namespace App\Controller\Api\v1;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
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
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $transactionRepository = $entityManager->getRepository(Transaction::class);
        $transactionQuery = $transactionRepository->createQueryBuilder('t');

        $typeNormalized = match($type) {
            "payment" => 0,
            "deposit" => 1,
            default => null
        };

        if (null !== $type) {
            $transactionQuery->andWhere('t.operationType = :type')->setParameter('type', $typeNormalized);
        }
        $transactionQuery->innerJoin('t.course', 'c');

        if (null !== $courseCode) {
            $transactionQuery->andWhere('c.symbolic_name = :courseCode')->setParameter('courseCode', $courseCode);
        }
        if ($skipExpired) {
            $transactionQuery->andWhere('t.valid_until > CURRENT_TIMESTAMP');
        }

        $transactions = $transactionQuery->getQuery()->getResult();
        return $this->json(array_map(function($transaction) {
            $normalized = [];
            $normalized["id"] = $transaction['t.id'];
            $normalized["created_at"] = $transaction["t.transaction_time"];
            $normalized["type"] = match ($transaction["t.operation_type"]) {0 => "payment", 1 => "deposit"};
            if (0 === $transaction["t.operation_type"]){
                $normalized["course_code"] = $transaction["c.symbolic_name"];
            }
            $normalized["amount"] = $transaction["t.value"];
            return $normalized;
        }, $transactions));
    }
}
