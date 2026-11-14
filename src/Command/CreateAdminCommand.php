<?php

namespace App\Command;

use App\Entity\AdminUser;
use App\Repository\AdminUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an administrator account (AdminUser)',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminUserRepository $adminUserRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getOption('email'));
        $password = (string) $input->getOption('password');

        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Provide a valid --email.');

            return Command::FAILURE;
        }

        if (strlen($password) < 6) {
            $io->error('Provide --password with at least 6 characters.');

            return Command::FAILURE;
        }

        $admin = $this->adminUserRepository->findOneBy(['email' => $email]);

        if ($admin) {
            $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
            $admin->setRoles(['ROLE_ADMIN']);
            $this->entityManager->flush();

            $io->success(sprintf('Admin account updated: %s', $email));

            return Command::SUCCESS;
        }

        $admin = new AdminUser();
        $admin->setEmail($email);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Admin account created: %s', $email));

        return Command::SUCCESS;
    }
}
