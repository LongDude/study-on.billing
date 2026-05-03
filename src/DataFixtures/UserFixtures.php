<?php

namespace App\DataFixtures;

use App\Entity\Transaction;
use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PaymentService $paymentService
    ){
    }

    public function load(ObjectManager $manager): void
    {
        // User 1
        $user1 = new User();
        $plain_password = "user_plain_password";
        $hashed_password = $this->passwordHasher->hashPassword($user1, $plain_password);
        $user1->setEmail("user@email.index");
        $user1->setRoles(["ROLE_USER"]);
        $user1->setPassword($hashed_password);
        $user1->setBalance(0);
        $manager->persist($user1);

        // User 2
        $user2 = new User();
        $plain_password = "user2_plain_password";
        $hashed_password = $this->passwordHasher->hashPassword($user2, $plain_password);
        $user2->setEmail("user2@email.index");
        $user2->setRoles(["ROLE_USER"]);
        $user2->setPassword($hashed_password);
        $user2->setBalance(0);
        $manager->persist($user2);

        // Admin
        $user_admin = new User();
        $plain_password = "user_admin_password";
        $hashed_password = $this->passwordHasher->hashPassword($user_admin, $plain_password);
        $user_admin->setEmail("admin@email.index");
        $user_admin->setRoles(["ROLE_SUPER_ADMIN"]);
        $user_admin->setPassword($hashed_password);
        $user_admin->setBalance(0);
        $manager->persist($user_admin);

        // Initial deposits
        $timeNow = new \DateTime();
        $deposits = [[$user1, 1000], [$user1, 1200], [$user2, 700], [$user2, 850]];
        foreach ($deposits as $depositData) {
            $this->paymentService->deposit($depositData[0], $depositData[1]);
        }

        $manager->flush();
    }
}
