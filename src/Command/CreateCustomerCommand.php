<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PartnerOnboardingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-customer',
    description: 'Create or update the default partner customer account',
)]
class CreateCustomerCommand extends Command
{
    private const DEFAULT_EMAIL = 'partner@example.com';
    private const DEFAULT_PASSWORD = 'partner123';
    private const DEFAULT_FULL_NAME = 'Test Partner';
    private const DEFAULT_PHONE = '+380000000000';
    private const DEFAULT_BALANCE = '100.00';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private PartnerOnboardingService $partnerOnboardingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Customer email', self::DEFAULT_EMAIL)
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Customer password', self::DEFAULT_PASSWORD)
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Full name', self::DEFAULT_FULL_NAME)
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Phone', self::DEFAULT_PHONE)
            ->addOption('balance', null, InputOption::VALUE_REQUIRED, 'Starting balance', self::DEFAULT_BALANCE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getOption('email'));
        $password = (string) $input->getOption('password');
        $fullName = trim((string) $input->getOption('name'));
        $phone = trim((string) $input->getOption('phone'));
        $balance = trim((string) $input->getOption('balance'));

        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Provide a valid --email.');

            return Command::FAILURE;
        }

        if (strlen($password) < 6) {
            $io->error('Provide --password with at least 6 characters.');

            return Command::FAILURE;
        }

        if ('' === $fullName) {
            $io->error('Provide a non-empty --name.');

            return Command::FAILURE;
        }

        if (!is_numeric($balance)) {
            $io->error('Provide a numeric --balance.');

            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNew = null === $user;

        if ($isNew) {
            $user = new User();
            $user->setEmail($email);
            $this->entityManager->persist($user);
        }

        $user->setFullName($fullName);
        $user->setPhone('' !== $phone ? $phone : null);
        $user->setBalance(number_format((float) $balance, 2, '.', ''));
        $user->setRoles([]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        if ($isNew) {
            $this->partnerOnboardingService->onboard($user);
        } else {
            $this->partnerOnboardingService->ensureOwnedTeam($user);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Customer account %s: %s (password: %s)',
            $isNew ? 'created' : 'updated',
            $email,
            $password,
        ));

        return Command::SUCCESS;
    }
}
