<?php

namespace App\Controller\Api\v1;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route("/api/v1")]
final class UsersController extends AbstractController
{

    #[Route('/users/current', name: 'api_current_user', methods: ['GET'])]
    #[OA\Get(
        path: "/api/v1/users/current",
        description: "Get current user",
        summary: "Return user info, using Bearer JWT token",
        security: [["Bearer" => []]],
        tags: ["Auth"],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "username",
                            description: "User email",
                            type: "string",
                            example: "user@mail.index"
                        ),
                        new OA\Property(
                            property: "roles",
                            description: "User roles",
                            type: "array",
                            items: new OA\Items(type: "string"),
                            example: ["ROLE_USER", "ROLE_ADMIN"]
                        ),
                        new OA\Property(
                            property: "balance",
                            description: "User balance",
                            type: "float",
                            example: "17.2"
                        ),
                    ],
                    type: "object",
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthorized",
            )
        ],
    )]
    public function getCurrentUser(
        #[CurrentUser] User $user
    ): JsonResponse
    {
        // /api/v1 защищенный маршрут для авторизированных пользователей
        // полагаем что проверка на существование пользователя уже провелась в JWT Auth
        return $this->json([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ]);
    }
}
