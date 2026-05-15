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
    private string $type;

    #[Assert\Length(min: 1, max: 255)]
    private string $title;

    #[Assert\Length(min: 1, max: 255)]
    private string $code;

    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private float $price;
}
