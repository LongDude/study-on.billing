<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[UniqueEntity('symbolic_name')]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(
        length: 255,
        unique: true
    )]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $symbolic_name = null;

    /**
     * License types:
     * 0 - free
     * 1 - rent
     * 2 - buy
     * @var int|null
     */
    #[ORM\Column(
        type: Types::SMALLINT,
        options: ['default' => 0]
    )]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(2)]
    private ?int $course_type = null;

    #[ORM\Column(
        options: ['default' => 0]
    )]
    #[Assert\AtLeastOneOf(
        [
            new Assert\GreaterThanOrEqual(0),
            new Assert\IsNull()
        ]
    )]
    private ?float $price = null;

    #[ORM\Column(
        nullable: false,
    )]
    #[Assert\NotNull]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;
    /**
     * @var Collection<int, Transaction>
     */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'Course')]
    private Collection $transactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSymbolicName(): ?string
    {
        return $this->symbolic_name;
    }

    public function setSymbolicName(string $symbolic_name): static
    {
        $this->symbolic_name = $symbolic_name;

        return $this;
    }

    public function getCourseType(): ?int
    {
        return $this->course_type;
    }

    public function setCourseType(int $course_type): static
    {
        $this->course_type = $course_type;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setCourse($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getCourse() === $this) {
                $transaction->setCourse(null);
            }
        }

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }
}
