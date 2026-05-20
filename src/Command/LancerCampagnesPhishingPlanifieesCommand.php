<?php

namespace App\Command;

use App\Entity\CampagnePhishing;
use App\Service\PhishingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:phishing:lancer-planifiees',
    description: 'Lance automatiquement les campagnes phishing planifiées dont la date de planification est passée.',
)]
class LancerCampagnesPhishingPlanifieesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PhishingService $phishingService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTime();

        $campagnes = $this->entityManager->getRepository(CampagnePhishing::class)
            ->createQueryBuilder('c')
            ->where('c.statut = :statut')
            ->setParameter('statut', 'PLANIFIEE')
            ->getQuery()
            ->getResult();

        if (empty($campagnes)) {
            $io->success('Aucune campagne phishing planifiée à lancer pour le moment.');
            return Command::SUCCESS;
        }

        $io->title('Lancement automatique des campagnes phishing planifiées');
        $summary = [];
        $hasError = false;

        foreach ($campagnes as $campagne) {
            try {
                $result = $this->phishingService->lancerCampagne($campagne);
                $this->entityManager->flush();

                $summary[] = sprintf(
                    'Campagne #%d "%s" lancée : %d envoi(s) envoyés, %d échec(s).',
                    $campagne->getId(),
                    $campagne->getTitre(),
                    $result['envoyes'],
                    $result['echoues']
                );
            } catch (\Throwable $e) {
                $hasError = true;
                $summary[] = sprintf(
                    'Campagne #%d "%s" : échec lors du lancement (%s).',
                    $campagne->getId(),
                    $campagne->getTitre(),
                    $e->getMessage()
                );
            }
        }

        $io->listing($summary);

        if ($hasError) {
            $io->warning('Certaines campagnes n’ont pas pu être lancées correctement.');
            return Command::FAILURE;
        }

        $io->success('Toutes les campagnes planifiées ont été lancées avec succès.');
        return Command::SUCCESS;
    }
}
