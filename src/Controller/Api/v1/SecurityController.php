<?php

namespace App\Controller\Api\v1;

use App\DTO\RegisterUserDto;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1')]
final class SecurityController extends AbstractController
{
    #[Route('/register', name: 'api_registration_contoller', methods: ['POST'])]
    #[OA\Post(
        path: "/api/v1/register",
        description: "Creates new user and returns JWT token",
        summary: "Create user",
        security: [],
        requestBody: new OA\RequestBody(
            description: "Register user",
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    ref: new Model(type: RegisterUserDto::class),
                )
            )
        ),
        tags: ["Auth"],
        responses: [
            new OA\Response(
                response: 201,
                description: 'User successfully registered',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/Token'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - validation failed or user already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "errors",
                            description: "Error message",
                            type: "string",
                            example: "Email should not be blank",
                        )
                    ],
                    type: "object"
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "errors",
                            description: "Error message",
                            type: "string",
                            example: "Server error"
                        )
                    ],
                    type: "object"
                )
            ),

        ]
    )]
    public function register(
        Request $request,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $JWTTokenManager,
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
            'token' => $JWTTokenManager->create($user),
        ], Response::HTTP_CREATED);
    }
}
