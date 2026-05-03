<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use \ValueError as ValueError;
use Webmozart\Assert\Assert;

class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,

        #[Autowire('app.rent_length')]
        private readonly string $rent_length,
    )
    {
    }

    /**
     * @param float $sum
     * @return void
     * @throws UserNotFoundException|Exception
     * @throws ValueError для отрицательной суммы пополнения
     */
    public function deposit(User $user, float $sum): void{
        $conn = $this->entityManager->getConnection();
        try {
            $conn->beginTransaction();

            if ($sum < 0) {
                throw new ValueError("Сумма депозита не может быть отрицательной");
            }

            $transaction = new Transaction();
            $transaction->setBillingUser($user);
            $transaction->setOperationType(1);
            $transaction->settransactionTime(new \DateTime());
            $transaction->setValue($sum);
            $this->entityManager->persist($transaction);

            $user->setBalance($user->getBalance() + $sum);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $conn->commit();
        } catch (\Doctrine\DBal\Exception $exception) {
            $conn->rollBack();
            throw $exception;
        }
    }

    public function payment(User $user, Course $course): ?Transaction {
        $conn = $this->entityManager->getConnection();
        try {
            $conn->beginTransaction();

            if ($user->getBalance() < $course->getPrice()){
                return null;
            }

            $transaction = new Transaction();
            $transaction->setBillingUser($user);
            $transaction->setCourse($course);
            $transaction->setOperationType(0);
            $transaction->settransactionTime(new \DateTime());
            if ($course->getCourseType() === 1) {
                $transaction->setValidUntil(new \DateTime()->modify('+'. $this->rent_length .' days'));
            }
            $transaction->setValue($course->getPrice());

            $user->setBalance($user->getBalance() - $course->getPrice());
            $this->entityManager->persist($transaction);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $conn->commit();
        } catch (\Doctrine\DBal\Exception $exception) {
            $conn->rollBack();
            throw $exception;
        }
        return $transaction;
    }
}
