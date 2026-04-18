<?php

namespace App\Controller\Api\v1;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route("/api/v1")]
final class UsersController extends AbstractController
{

    #[Route('/users/current', name: 'api_current_user', methods: ['GET'])]
    public function getCurrentUser(
        TokenStorageInterface $tokenStorage,
        JWTTokenManagerInterface $jwtManager,
        #[CurrentUser] User $user
    ): JsonResponse
    {
        // /api/v1 защищенный маршрут для авторизированных пользователей
        // полагаем что проверка на существование пользователя уже провелась в JWT Auth
        return $this->json([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ], 200);
    }
}
