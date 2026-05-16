<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Service\PaymentService;
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
            ->setTitle("Основы веб-разработки")
            ->setSymbolicName("web-development-basics")
            ->setCourseType(0)
            ->setPrice(0);
        $manager->persist($course1);

        $course2 = new Course()
            ->setTitle("Python для анализа данных")
            ->setSymbolicName("python-for-data-science")
            ->setCourseType(0)
            ->setPrice(0);
        $manager->persist($course2);

        $course3 = new Course()
            ->setTitle("Symfony: от новичка до профи")
            ->setSymbolicName("symfony-framework-mastery")
            ->setPrice(199.99)
            ->setCourseType(1); // rent
        $manager->persist($course3);

        $course4 = new Course()
            ->setTitle("Проектирование и оптимизация SQL баз данных")
            ->setSymbolicName("sql-database-design")
            ->setPrice(5000)
            ->setCourseType(2); // paid
        $manager->persist($course4);

        $course5 = new Course()
            ->setTitle("Docker для разработчиков")
            ->setSymbolicName("docker-for-developers")
            ->setPrice(4000)
            ->setCourseType(2); // paid
        $manager->persist($course5);
        $manager->flush();
    }
}
