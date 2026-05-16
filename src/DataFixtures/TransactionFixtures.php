<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TransactionFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [ CourseFixtures::class ];
    }

    public function load(ObjectManager $manager): void
    {
        // Users for transactions
        $user1 = $manager->getRepository(User::class)->findOneBy(['email' => 'user@email.index']);
        $user2 = $manager->getRepository(User::class)->findOneBy(['email' => 'user2@email.index']);

        $course1 = $manager->getRepository(Course::class)->findOneBy(['symbolic_name' => 'web-development-basics']);
        $course2 = $manager->getRepository(Course::class)->findOneBy(['symbolic_name' => 'python-for-data-science']);
        $course3 = $manager->getRepository(Course::class)->findOneBy(['symbolic_name' => 'symfony-framework-mastery']);
        $course4 = $manager->getRepository(Course::class)->findOneBy(['symbolic_name' => 'sql-database-design']);
        $course5 = $manager->getRepository(Course::class)->findOneBy(['symbolic_name' => 'docker-for-developers']);

        // Free courses
        $manager->persist(
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course1)
                ->setValue(0)
                ->setTransactionTime(new \DateTime()->modify('-1 day'))
                ->setOperationType(0)
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course2)
                ->setValue(0)
                ->setTransactionTime(new \DateTime()->modify('-1 day'))
                ->setOperationType(0)
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course1)
                ->setValue(0)
                ->setTransactionTime(new \DateTime()->modify('-1 day'))
                ->setOperationType(0)
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course2)
                ->setValue(0)
                ->setTransactionTime(new \DateTime()->modify('-1 day'))
                ->setOperationType(0)
        );

        // Paid courses (perpetual license)
        $manager->persist(
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course4)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-15 day'))
                ->setValue($course4->getPrice())
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course5)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-30 day'))
                ->setValue($course5->getPrice())
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course4)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-15 day'))
                ->setValue($course4->getPrice())
        );
        $manager->persist(
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course5)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-45 day'))
                ->setValue($course5->getPrice())
        );

        // Rent courses
        $manager->persist( // User 1 - invalid
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-75 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil(new \DateTime()->modify('-45 day'))
        );
        $manager->persist( // User 1 - invalid
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-45 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil(new \DateTime()->modify('-15 day'))
        );
        $manager->persist( // User 1 - valid
            new Transaction()
                ->setBillingUser($user1)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-15 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil(new \DateTime()->modify('+15 day'))
        );

        $manager->persist( // User 2 - invalid
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-75 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil(new \DateTime()->modify('-45 day'))
        );
        $manager->persist( // User 2 - invalid
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-45 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil(new \DateTime()->modify('-15 day'))
        );
        $manager->persist( // User 2 - valid
            new Transaction()
                ->setBillingUser($user2)
                ->setCourse($course3)
                ->setOperationType(0)
                ->settransactionTime(new \DateTime()->modify('-6 day'))
                ->setValue($course3->getPrice())
                ->setValidUntil(new \DateTime()->modify('+1 day'))
        );
        $manager->flush();

    }
}
