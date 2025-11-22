<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getGroups(): array
    {
        // Separate group to avoid touching other data when loading
        return ['users'];
    }

    public function load(ObjectManager $manager): void
    {
        // Admin with secure password: Admin13005!@#Secure
        // Requirements: 12+ chars, uppercase, lowercase, digit, special char
        $admin = new User();
        $admin->setEmail('admin@letroisquarts.online')
              ->setName('Admin')
              ->setRole(UserRole::ADMIN)
              ->setIsActive(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin13005!@#Secure'));
        $manager->persist($admin);

        // Moderator with secure password: Moder13005!@#Secure
        // Requirements: 12+ chars, uppercase, lowercase, digit, special char
        $moderator = new User();
        $moderator->setEmail('moderator@letroisquarts.online')
                  ->setName('Moderator')
                  ->setRole(UserRole::MODERATOR)
                  ->setIsActive(true);
        $moderator->setPassword($this->passwordHasher->hashPassword($moderator, 'Moder13005!@#Secure'));
        $manager->persist($moderator);

        $manager->flush();
    }
}


