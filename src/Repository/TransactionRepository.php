<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
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

    public function findEndingRentals(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                'u.email as email',
                'c.title as course_name',
                't.validUntil as valid_until'
            )
            ->innerJoin('t.BillingUser', 'u')
            ->innerJoin('t.Course', 'c')
            ->andWhere('t.operationType = :operationType')->setParameter('operationType', 0)
            ->andWhere('c.course_type = :courseType')->setParameter('courseType', 1)
            ->andWhere('t.validUntil >= :from')->setParameter('from', $from)
            ->andWhere('t.validUntil < :to')->setParameter('to', $to)
            ->orderBy('u.email', 'ASC')
            ->addOrderBy('t.validUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getPaidCoursesReport(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                'c.title as course_name',
                'c.course_type as course_type',
                'COUNT(t.id) as payments_count',
                'SUM(t.value) as total_sum'
            )
            ->innerJoin('t.Course', 'c')
            ->andWhere('t.operationType = :operationType')->setParameter('operationType', 0)
            ->andWhere('c.course_type IN (:courseTypes)')->setParameter('courseTypes', [1, 2])
            ->andWhere('t.transactionTime >= :from')->setParameter('from', $from)
            ->andWhere('t.transactionTime < :to')->setParameter('to', $to)
            ->groupBy('c.id')
            ->addGroupBy('c.title')
            ->addGroupBy('c.course_type')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function listActiveCourses(?User $user, ?string $symbolicCode = null): array
    {
        $q = $this->createQueryBuilder('t')
            ->select(
                'c.symbolic_name as code',
                't.validUntil as valid_until',
            )
            ->where('t.BillingUser = :user')->setParameter('user', $user)
            ->andWhere('t.operationType = :type')->setParameter('type', 0)
            ->andWhere('t.validUntil is null or t.validUntil > :timenow')->setParameter('timenow', new \DateTime())
            ->leftJoin('t.Course', 'c');
        if ($symbolicCode) {
            $q = $q->andWhere('c.symbolic_name = :code')->setParameter('code', $symbolicCode);
        }
        return $q->getQuery()->getResult();
    }

    public function listFiltered(?User $user, ?string $type, ?string $courseCode, ?bool $skipExpired): array
    {
        $transactionQuery = $this->createQueryBuilder('t')
            ->select(
                't.id as id',
                't.transactionTime as created_at',
                't.operationType as type',
                'c.symbolic_name as course_code',
                't.value as amount'
            );

        if ($user){
            $transactionQuery->andWhere('t.BillingUser = :user')->setParameter('user', $user);
        }

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
        $transactionQuery->orderBy('t.transactionTime', 'DESC');
        return $transactionQuery->getQuery()->getResult();
    }
}
