<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class CoursePostDTO
{
    #[Assert\NotBlank]
    #[Assert\AtLeastOneOf([
        new Assert\EqualTo('free'),
        new Assert\EqualTo('rent'),
        new Assert\EqualTo('buy'),
    ])]
    public string $type;

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    public string $title;

    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    public string $code;

    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    public float $price;
}
