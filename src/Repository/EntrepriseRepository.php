<?php

namespace App\Repository;

use App\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entreprise>
 */
class EntrepriseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entreprise::class);
    }

    /**
     * Trouver les entreprises en attente de validation
     */
    public function findEnAttente(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.statut = :statut')
            ->setParameter('statut', 'EN_ATTENTE')
            ->orderBy('e.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les entreprises actives
     */
    public function findActives(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.statut = :statut')
            ->setParameter('statut', 'ACTIF')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher une entreprise par IFU
     */
    public function findByIfu(string $ifu): ?Entreprise
    {
        return $this->createQueryBuilder('e')
            ->where('e.ifu = :ifu')
            ->setParameter('ifu', $ifu)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compter les entreprises par statut
     */
    public function countByStatut(string $statut): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }
}