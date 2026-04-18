<?php

namespace App\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final class RegisterUserDto
{
    #[Assert\NotBlank(message: "Email should not be blank")]
    #[Assert\Email(message:"Invalid email address")]
    public string $email;

    #[Assert\NotBlank(message: "Password should not be blank")]
    #[Assert\Length(min: 6, minMessage: 'Password should be at least {{ limit }} characters long.')]
    public string $password;
}
