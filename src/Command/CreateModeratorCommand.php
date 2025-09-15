<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-moderator',
    description: 'Créer un utilisateur modérateur',
)]
class CreateModeratorCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email du modérateur')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe du modérateur')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nom du modérateur')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email') ?: $io->ask('Email du modérateur');
        $password = $input->getOption('password') ?: $io->askHidden('Mot de passe du modérateur');
        $name = $input->getOption('name') ?: $io->ask('Nom du modérateur');

        if (!$email || !$password || !$name) {
            $io->error('Tous les champs sont requis.');
            return Command::FAILURE;
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error('Un utilisateur avec cet email existe déjà.');
            return Command::FAILURE;
        }

        // Créer l'utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRole('ROLE_MODERATOR');
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Modérateur créé avec succès: %s (%s)', $name, $email));

        return Command::SUCCESS;
    }
}
