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

#[AsCommand(
    name: 'app:update-user-emails',
    description: 'Update user email addresses from old domain to new domain',
)]
class UpdateUserEmailsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('old-domain', null, InputOption::VALUE_REQUIRED, 'Old email domain (e.g., letroisquarts.com)', 'letroisquarts.com')
            ->addOption('new-domain', null, InputOption::VALUE_REQUIRED, 'New email domain (e.g., letroisquarts.online)', 'letroisquarts.online')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without actually updating')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force update without confirmation')
            ->setHelp('This command updates user email addresses from one domain to another. Example: app:update-user-emails --old-domain=letroisquarts.com --new-domain=letroisquarts.online');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $oldDomain = $input->getOption('old-domain');
        $newDomain = $input->getOption('new-domain');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        // Find all users with old domain
        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.email LIKE :domain')
            ->setParameter('domain', '%@' . $oldDomain)
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            $io->info(sprintf('No users found with email domain: @%s', $oldDomain));
            return Command::SUCCESS;
        }

        $io->title('User Email Update');
        $io->section(sprintf('Found %d user(s) with domain @%s', count($users), $oldDomain));

        // Show what will be changed
        $io->table(
            ['ID', 'Name', 'Current Email', 'New Email', 'Role'],
            array_map(function (User $user) use ($oldDomain, $newDomain) {
                $newEmail = str_replace('@' . $oldDomain, '@' . $newDomain, $user->getEmail());
                return [
                    $user->getId(),
                    $user->getName(),
                    $user->getEmail(),
                    $newEmail,
                    $user->getRole()->value,
                ];
            }, $users)
        );

        if ($dryRun) {
            $io->note('Dry run mode - no changes were made');
            return Command::SUCCESS;
        }

        // Confirm before updating
        if (!$force) {
            if (!$io->confirm(sprintf('Update %d user email(s) from @%s to @%s?', count($users), $oldDomain, $newDomain), false)) {
                $io->warning('Update cancelled');
                return Command::SUCCESS;
            }
        }

        // Update emails
        // IMPORTANT: This command ONLY modifies the 'email' field in the 'users' table.
        // It does NOT affect:
        // - User ID (primary key, never changes)
        // - Password hashes
        // - User roles
        // - Other user fields (name, isActive, createdAt, lastLoginAt)
        // - Related entities (ContactMessage.repliedBy uses User ID, not email)
        $updated = 0;
        foreach ($users as $user) {
            $oldEmail = $user->getEmail();
            $newEmail = str_replace('@' . $oldDomain, '@' . $newDomain, $oldEmail);
            
            // Validate new email format
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $io->error(sprintf('Invalid email format: %s (skipping)', $newEmail));
                continue;
            }
            
            // Check if new email already exists
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $newEmail]);
            
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $io->warning(sprintf('Skipping %s - email %s already exists', $oldEmail, $newEmail));
                continue;
            }

            // Only modify email field - all other fields remain unchanged
            $user->setEmail($newEmail);
            $this->entityManager->persist($user);
            $updated++;
        }

        // Flush changes - only email fields will be updated in database
        $this->entityManager->flush();

        $io->success(sprintf('Successfully updated %d user email(s)', $updated));

        return Command::SUCCESS;
    }
}

