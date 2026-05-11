<?php

namespace App\Controller\Api\v1;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use OpenApi\Attributes as OA;
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
    #[OA\Get(
        path: '/api/v1/transactions',
        description: 'Returns current user transactions with optional filters by operation type, course code and expiration state.',
        summary: 'List transactions',
        security: [['Bearer' => []]],
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                description: 'Transaction type filter',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['payment', 'deposit']),
                example: 'payment',
            ),
            new OA\Parameter(
                name: 'course_code',
                description: 'Course symbolic code filter',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string'),
                example: 'sql-database-design',
            ),
            new OA\Parameter(
                name: 'skip_expired',
                description: 'When true, expired rent transactions are excluded',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                example: true,
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transactions list',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', description: 'Transaction identifier', type: 'integer', example: 42),
                            new OA\Property(property: 'created_at', description: 'ISO 8601 transaction creation date', type: 'string', format: 'date-time', example: '2026-05-12T15:30:00+03:00'),
                            new OA\Property(property: 'type', description: 'Transaction type', type: 'string', enum: ['payment', 'deposit'], example: 'payment'),
                            new OA\Property(property: 'course_code', description: 'Course symbolic code. Not returned for deposit transactions.', type: 'string', example: 'sql-database-design'),
                            new OA\Property(property: 'amount', description: 'Transaction amount', type: 'number', format: 'float', example: 5000),
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
        ],
    )]
    public function listTransactions(
        #[CurrentUser] User $user,
        #[MapQueryParameter("type")] ?string $type,
        #[MapQueryParameter("course_code")] ?string $courseCode,
        #[MapQueryParameter("skip_expired")] ?bool $skipExpired,
        TransactionRepository $transactionRepository,
    ): JsonResponse
    {
        $transactions = $transactionRepository->listFiltered($user, $type, $courseCode, $skipExpired);
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
    #[OA\Post(
        path: '/api/v1/transactions/deposit',
        description: 'Adds money to the current user balance.',
        summary: 'Deposit balance',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Deposit amount',
            required: true,
            content: new OA\JsonContent(
                required: ['deposit'],
                properties: [
                    new OA\Property(property: 'deposit', description: 'Positive deposit amount', type: 'number', format: 'float', example: 1000),
                ],
                type: 'object',
            ),
        ),
        tags: ['Transactions'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Balance successfully deposited',
                content: new OA\MediaType(
                    mediaType: 'text/plain',
                    schema: new OA\Schema(type: 'string', example: 'Success'),
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid deposit amount',
                content: new OA\MediaType(
                    mediaType: 'text/plain',
                    schema: new OA\Schema(type: 'string', example: 'Неверная сумма пополнения'),
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
                        new OA\Property(property: 'error', type: 'string', example: 'Сервис временно недоступен'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
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
