<?php

namespace App\DTO;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: "RegisterUserDto",
    title: "RegisterUserDto",
    description: "User registration credentials",
    required: ["email", "password"],
)]
final class RegisterUserDto
{
    #[OA\Property(
        description: "User's email address",
        type: "string",
        format: "email",
        example: "user@email.index"
    )]
    #[Assert\NotBlank(
        message: "Email should not be blank"
    )]
    #[Assert\Email(
        message:"Invalid email address"
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: "The email address should be at most 255 characters long",
    )]
    public string $email;

    #[OA\Property(
        description: "User's password",
        type: "string",
        format: "password",
        example: "user_plain_password",
        minLength: 6,
    )]
    #[Assert\NotBlank(
        message: "Password should not be blank"
    )]
    #[Assert\Length(
        min: 6,
        max: 255,
        minMessage: 'Password should be at least {{ limit }} characters long.',
        maxMessage: 'Password should be at most {{ limit }} characters long.')]
    public string $password;
}
