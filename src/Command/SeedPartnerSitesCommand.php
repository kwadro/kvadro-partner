<?php

namespace App\Command;

use App\Entity\PartnerSite;
use App\Repository\PartnerSiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-partner-sites', description: 'Seed demo partner sites')]
class SeedPartnerSitesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PartnerSiteRepository $partnerSiteRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sites = [
            ['partnerId' => 101, 'url' => 'https://shop-alpha.example.com'],
            ['partnerId' => 202, 'url' => 'https://market-beta.example.com'],
            ['partnerId' => 303, 'url' => 'https://store-gamma.example.com'],
        ];

        $created = 0;
        foreach ($sites as $data) {
            $existing = $this->partnerSiteRepository->findOneBy([
                'partnerId' => $data['partnerId'],
            ]);
            if ($existing) {
                continue;
            }

            $site = (new PartnerSite())
                ->setPartnerId($data['partnerId'])
                ->setUrl($data['url']);
            $this->entityManager->persist($site);
            ++$created;
        }

        $this->entityManager->flush();
        $io->success(sprintf('Partner sites ready (%d new).', $created));

        return Command::SUCCESS;
    }
}
