<?php

namespace App\Repository;

use App\Entity\EnvoiPhishing;
use App\Entity\CampagnePhishing;
use App\Entity\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EnvoiPhishingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnvoiPhishing::class);
    }

    /**
     * Trouver tous les envois d'une campagne
     */
    public function findByCampagne(CampagnePhishing $campagne): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('e.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver tous les envois pour un employé donné
     */
    public function findByEmploye(Employe $employe): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('e.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les envois échoués (pour retry)
     */
    public function findEchoues(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', EnvoiPhishing::STATUT_ECHOUE)
            ->andWhere('e.nombreTentatives < 3')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les envois par statut pour une campagne
     */
    public function countByStatutForCampagne(CampagnePhishing $campagne): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('e.statut, COUNT(e.id) as total')
            ->andWhere('e.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->groupBy('e.statut')
            ->getQuery()
            ->getResult();

        $counts = [
            EnvoiPhishing::STATUT_PLANIFIE => 0,
            EnvoiPhishing::STATUT_ENVOYE   => 0,
            EnvoiPhishing::STATUT_ECHOUE   => 0,
        ];

        foreach ($result as $row) {
            $counts[$row['statut']] = (int) $row['total'];
        }

        return $counts;
    }
}