<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?User $BillingUser = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Course $Course = null;

    /**
     * Operation type:
     * 0 - payment
     * 1 - deposit
     * @var int|null
     */
    #[ORM\Column(
        type: Types::SMALLINT
    )]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(1)]
    private ?int $operationType = null;

    #[ORM\Column(
        options: ['default' => 0]
    )]
    #[Assert\Positive]
    private ?float $value = null;

    #[ORM\Column(
        options: ['default' => 'CURRENT_TIMESTAMP']
    )]
    private ?\DateTime $transactionTime = null;

    #[ORM\Column(
        options: ['default' => '(CURRENT_TIMESTAMP + INTERVAL \'30 days\')::timestamp']
    )]
    private ?\DateTime $validUntil = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBillingUser(): ?User
    {
        return $this->BillingUser;
    }

    public function setBillingUser(?User $BillingUser): static
    {
        $this->BillingUser = $BillingUser;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->Course;
    }

    public function setCourse(?Course $Course): static
    {
        $this->Course = $Course;

        return $this;
    }

    public function getOperationType(): ?int
    {
        return $this->operationType;
    }

    public function setOperationType(int $operationType): static
    {
        $this->operationType = $operationType;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function gettransactionTime(): ?\DateTime
    {
        return $this->transactionTime;
    }

    public function settransactionTime(\DateTime $transactionTime): static
    {
        $this->transactionTime = $transactionTime;

        return $this;
    }

    public function getValidUntil(): ?\DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTime $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }
}
