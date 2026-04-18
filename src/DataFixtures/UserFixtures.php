<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    public function __construct(UserPasswordHasherInterface $encoder){
        $this->passwordHasher = $encoder;
    }

    public function load(ObjectManager $manager): void
    {
        // Default user
        $user_user = new User();
        $plain_password = "user_plain_password";
        $hashed_password = $this->passwordHasher->hashPassword($user_user, $plain_password);
        $user_user->setEmail("user@email.index");
        $user_user->setRoles(["ROLE_USER"]);
        $user_user->setPassword($hashed_password);
        $user_user->setBalance(17.2);
        $manager->persist($user_user);

        // Admin
        $user_admin = new User();
        $plain_password = "user_admin_password";
        $hashed_password = $this->passwordHasher->hashPassword($user_user, $plain_password);
        $user_admin->setEmail("admin@email.index");
        $user_admin->setRoles(["ROLE_ADMIN"]);
        $user_admin->setPassword($hashed_password);
        $user_admin->setBalance(42.3);
        $manager->persist($user_admin);

        $manager->flush();
    }
}
