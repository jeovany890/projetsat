<?php

namespace App\Repository;

use App\Entity\CampagnePhishing;
use App\Entity\Employe;
use App\Entity\ResultatPhishing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ResultatPhishingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResultatPhishing::class);
    }

    public function findByCampagne(CampagnePhishing $campagne): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('r.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPlanifiesPourCampagne(CampagnePhishing $campagne): array
    {
        return $this->findBy([
            'campagne' => $campagne,
            'statut'   => ResultatPhishing::STATUT_PLANIFIE,
        ]);
    }

    public function findEchoues(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.statut = :statut')
            ->setParameter('statut', ResultatPhishing::STATUT_ECHOUE)
            ->andWhere('r.nombreTentatives < 3')
            ->getQuery()
            ->getResult();
    }

    public function countByStatutForCampagne(CampagnePhishing $campagne): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.statut, COUNT(r.id) as total')
            ->andWhere('r.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->groupBy('r.statut')
            ->getQuery()
            ->getResult();

        $counts = [
            ResultatPhishing::STATUT_PLANIFIE => 0,
            ResultatPhishing::STATUT_ENVOYE   => 0,
            ResultatPhishing::STATUT_ECHOUE   => 0,
        ];

        foreach ($result as $row) {
            $counts[$row['statut']] = (int) $row['total'];
        }

        return $counts;
    }

    public function findByEmploye(Employe $employe): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
