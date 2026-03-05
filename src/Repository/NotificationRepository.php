<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findNonLuesParUtilisateur(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.utilisateur = :utilisateur')
            ->andWhere('n.estLu = :estLu')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('estLu', false)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function compterNonLues(Utilisateur $utilisateur): int
    {
        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.utilisateur = :utilisateur')
            ->andWhere('n.estLu = :estLu')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('estLu', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}