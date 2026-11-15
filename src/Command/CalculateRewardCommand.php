<?php

namespace App\Command;

use App\Entity\Partner;
use App\Entity\User;
use App\Repository\PartnerRepository;
use App\Repository\UserRepository;
use App\Service\Reward\RewardCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:calculate-reward',
    description: 'Calculate rewards from a sale using a partner percent settings',
)]
class CalculateRewardCommand extends Command
{
    public function __construct(
        private RewardCalculator $rewardCalculator,
        private UserRepository $userRepository,
        private PartnerRepository $partnerRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('amount', null, InputOption::VALUE_REQUIRED, 'Order amount', '1000')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Seller email (optional; demo chain if omitted)')
            ->addOption('partner-id', null, InputOption::VALUE_REQUIRED, 'Partner id (optional; demo partner if omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $amount = (string) $input->getOption('amount');
        $email = trim((string) $input->getOption('email'));
        $partnerId = (int) $input->getOption('partner-id');

        if ($partnerId > 0) {
            $partner = $this->partnerRepository->find($partnerId);
            if (!$partner) {
                $io->error(sprintf('Partner not found: %d', $partnerId));

                return Command::FAILURE;
            }
        } else {
            $partner = (new Partner())
                ->setName('Demo Partner')
                ->setOwnPercent(10)
                ->setLevel1Percent(5)
                ->setLevel2Percent(2);
            $io->note('Demo partner percents: 10 / 5 / 2');
        }

        if ('' !== $email) {
            $seller = $this->userRepository->findOneBy(['email' => $email]);
            if (!$seller) {
                $io->error(sprintf('User not found: %s', $email));

                return Command::FAILURE;
            }
        } else {
            $level1 = (new User())->setEmail('demo-level1@example.com')->setFullName('Level 1');
            $level2 = (new User())->setEmail('demo-level2@example.com')->setFullName('Level 2')->setInvitedBy($level1);
            $seller = (new User())->setEmail('demo-level3@example.com')->setFullName('Level 3')->setInvitedBy($level2);
            $io->note('Demo chain: Level1 <- Level2 <- Level3 (seller)');
        }

        $calculation = $this->rewardCalculator->calculateFromSale($seller, $amount, $partner);

        $rows = [];
        foreach ($calculation->getLines() as $line) {
            $rows[] = [
                $line->getLevel(),
                $line->getBeneficiary()->getFullName() ?: $line->getBeneficiary()->getEmail(),
                $line->getPercent().'%',
                $line->getAmount(),
            ];
        }

        $io->table(['Level', 'Beneficiary', 'Percent', 'Amount'], $rows);
        $io->success(sprintf(
            'Order %s → total rewards %s (partner: %s)',
            $calculation->getOrderAmount(),
            $calculation->getTotalAmount(),
            $partner->getName(),
        ));

        return Command::SUCCESS;
    }
}
