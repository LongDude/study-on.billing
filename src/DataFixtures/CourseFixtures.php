<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [ UserFixtures::class ];
    }

    public function load(ObjectManager $manager): void
    {
        $course1 = new Course() // free
            ->setSymbolicName("web-development-basics")
            ->setCourseType(0)
            ->setPrice(0);
        $manager->persist($course1);

        $course2 = new Course()
            ->setSymbolicName("python-for-data-science")
            ->setCourseType(0)
            ->setPrice(0);
        $manager->persist($course2);

        $course3 = new Course()
            ->setSymbolicName("symfony-framework-mastery")
            ->setPrice(199.99)
            ->setCourseType(1); // rent
        $manager->persist($course3);

        $course4 = new Course()
            ->setSymbolicName("sql-database-design")
            ->setPrice(5000)
            ->setCourseType(2); // paid
        $manager->persist($course4);

        $course5 = new Course()
            ->setSymbolicName("docker-for-developers")
            ->setPrice(4000)
            ->setCourseType(2); // paid
        $manager->persist($course5);

        // Transactions
        $user1 = $manager->getRepository(User::class)->findOneBy(['email' => 'user@email.index']);
        $user2 = $manager->getRepository(User::class)->findOneBy(['email' => 'user2@email.index']);

        // deposits
        $timeNow = new \DateTime();
        $deposits = [[$user1, 1000], [$user1, 1200], [$user2, 700], [$user2, 850]];
        foreach ($deposits as $depositData) {
            $deposit = new Transaction()
                ->setBillingUser($depositData[0])
                ->setOperationType(1)
                ->setValue($depositData[1])
                ->settransactionTime((clone $timeNow)->modify('-1 day'));
            $manager->persist($deposit);
        }

        // Free courses
        $manager->persist(
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course1)
                ->setValue(0)
                ->setTransactionTime((clone $timeNow)->modify('-1 day'))
                ->setOperationType(0)
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course2)
                ->setValue(0)
                ->setTransactionTime((clone $timeNow)->modify('-1 day'))
                ->setOperationType(0)
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course1)
                ->setValue(0)
                ->setTransactionTime((clone $timeNow)->modify('-1 day'))
                ->setOperationType(0)
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course2)
                ->setValue(0)
                ->setTransactionTime((clone $timeNow)->modify('-1 day'))
                ->setOperationType(0)
        );

        // Paid courses (perpetual license)
        $manager->persist(
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course4)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-15 day'))
                ->setValue($course4->getPrice())
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course5)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-30 day'))
                ->setValue($course5->getPrice())
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course4)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-15 day'))
                ->setValue($course4->getPrice())
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course5)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-45 day'))
                ->setValue($course5->getPrice())
        );

        // Rent courses
        $manager->persist( // User 1 - invalid
            new Transaction()
            ->setBillingUser($user1)
            ->setCourse($course3)
            ->setOperationType(0)
            ->settransactionTime((clone $timeNow)->modify('-75 day'))
            ->setValue($course3->getPrice())
            ->setValidUntil((clone $timeNow)->modify('-45 day'))
        );
        $manager->persist( // User 1 - invalid
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-45 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil((clone $timeNow)->modify('-15 day'))
        );
        $manager->persist( // User 1 - valid
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-15 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil((clone $timeNow)->modify('+15 day'))
        );

        $manager->persist( // User 2 - invalid
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-75 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil((clone $timeNow)->modify('-45 day'))
        );
        $manager->persist( // User 2 - invalid
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-45 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil((clone $timeNow)->modify('-15 day'))
        );
        $manager->persist( // User 2 - valid
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime((clone $timeNow)->modify('-15 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil((clone $timeNow)->modify('+15 day'))
        );
        $manager->flush();
    }
}
