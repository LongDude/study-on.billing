<?php

namespace App\Controller\Api\v1;

use App\DTO\RegisterUserDto;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use JMS\Serializer\Serializer;

use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1')]
final class SecurityController extends AbstractController
{
    #[Route('/register', name: 'api_registration_contoller', methods: ['POST'])]
    public function register(
        Request $request,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse
    {
        $serializer = SerializerBuilder::create()->build();
        $userDto = $serializer->deserialize($request->getContent(), RegisterUserDto::class, 'json');
        $errors = $validator->validate($userDto);

        // Check validation
        if ($errors->count() > 0) {
            return $this->json(["errors" => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($userDto->email);
        $user->setPassword($passwordHasher->hashPassword($user, $userDto->password));
        $user->setRoles(['ROLE_USER']);

        // Check for constraint violation / database errors
        try {
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(["errors" => 'User with given email already exists'], Response::HTTP_BAD_REQUEST);
        } catch (ORMException $e) {
            if ("dev" === $this->container->get('kernel')->getEnvironment()) {
                return $this->json(["errors" => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            return $this->json(["errors" => "Server error"], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Return response
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/SecurityController.php',
        ]);
    }
}
