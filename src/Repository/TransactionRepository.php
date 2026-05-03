<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function listFiltered(?string $type, ?string $courseCode, ?bool $skipExpired): array
    {
        $transactionQuery = $this->createQueryBuilder('t')
            ->select(
                't.id as id',
                't.transactionTime as created_at',
                't.operationType as type',
                'c.symbolic_name as course_code',
                't.value as amount'
            );

        $typeNormalized = match($type) {
            "payment" => 0,
            "deposit" => 1,
            default => null
        };

        if (null !== $type) {
            $transactionQuery->andWhere('t.operationType = :type')->setParameter('type', $typeNormalized);
        }
        $transactionQuery->leftJoin('t.Course', 'c');

        if (null !== $courseCode) {
            $transactionQuery->andWhere('c.symbolic_name = :courseCode')->setParameter('courseCode', $courseCode);
        }
        if ($skipExpired) {
            $transactionQuery->andWhere('t.validUntil is null or t.validUntil > :timenow')->setParameter('timenow', new \DateTime());
        }
        return $transactionQuery->getQuery()->getResult();
    }

    //    /**
    //     * @return Transaction[] Returns an array of Transaction objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Transaction
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
