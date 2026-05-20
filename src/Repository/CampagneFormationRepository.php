<?php

namespace App\Repository;

use App\Entity\CampagneFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CampagneFormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CampagneFormation::class);
    }

    /**
     * Stats d'une seule campagne formation calculées en temps réel.
     * Remplace les anciens compteurs dénormalisés (totalParticipants, nombreTermines, etc.)
     *
     * @return array{totalParticipants:int, nombreTermines:int, nombreEnCours:int, nombreEnRetard:int}
     */
    public function getStats(CampagneFormation $campagne): array
    {
        $em  = $this->getEntityManager();
        $now = new \DateTime();

        $totalParticipants = (int) $em->createQueryBuilder()
            ->select('COUNT(DISTINCT p.employe)')
            ->from('App\Entity\ProgressionModule', 'p')
            ->where('p.campagne = :c')
            ->setParameter('c', $campagne)
            ->getQuery()->getSingleScalarResult();

        $nombreTermines = (int) $em->createQueryBuilder()
            ->select('COUNT(DISTINCT p.employe)')
            ->from('App\Entity\ProgressionModule', 'p')
            ->where('p.campagne = :c')
            ->andWhere('p.statut = :statut')
            ->setParameter('c', $campagne)
            ->setParameter('statut', 'TERMINE')
            ->getQuery()->getSingleScalarResult();

        $nombreEnCours = (int) $em->createQueryBuilder()
            ->select('COUNT(DISTINCT p.employe)')
            ->from('App\Entity\ProgressionModule', 'p')
            ->where('p.campagne = :c')
            ->andWhere('p.statut = :statut')
            ->setParameter('c', $campagne)
            ->setParameter('statut', 'EN_COURS')
            ->getQuery()->getSingleScalarResult();

        // En retard = EN_COURS ou NON_COMMENCE et date de fin dépassée
        $nombreEnRetard = 0;
        if ($campagne->getDateFin() < $now) {
            $nombreEnRetard = (int) $em->createQueryBuilder()
                ->select('COUNT(DISTINCT p.employe)')
                ->from('App\Entity\ProgressionModule', 'p')
                ->where('p.campagne = :c')
                ->andWhere('p.statut IN (:statuts)')
                ->setParameter('c', $campagne)
                ->setParameter('statuts', ['EN_COURS', 'NON_COMMENCE'])
                ->getQuery()->getSingleScalarResult();
        }

        return [
            'totalParticipants' => $totalParticipants,
            'nombreTermines'    => $nombreTermines,
            'nombreEnCours'     => $nombreEnCours,
            'nombreEnRetard'    => $nombreEnRetard,
        ];
    }

    /**
     * Stats de plusieurs campagnes en une seule passe (évite N+1).
     * Utilisé dans la liste des campagnes formation.
     *
     * @param CampagneFormation[] $campagnes
     * @return array<int, array{totalParticipants:int, nombreTermines:int, nombreEnCours:int, nombreEnRetard:int}>
     */
    public function getStatsParCampagnes(array $campagnes): array
    {
        if (empty($campagnes)) {
            return [];
        }

        $ids = array_map(fn($c) => $c->getId(), $campagnes);
        $em  = $this->getEntityManager();

        $rows = $em->createQueryBuilder()
            ->select(
                'IDENTITY(p.campagne) AS campagne_id',
                'COUNT(DISTINCT p.employe) AS totalParticipants',
                'SUM(CASE WHEN p.statut = \'TERMINE\' THEN 1 ELSE 0 END) AS nombreTermines',
                'SUM(CASE WHEN p.statut = \'EN_COURS\' THEN 1 ELSE 0 END) AS nombreEnCours',
                'SUM(CASE WHEN p.statut IN (\'EN_COURS\', \'NON_COMMENCE\') THEN 1 ELSE 0 END) AS potEnRetard'
            )
            ->from('App\Entity\ProgressionModule', 'p')
            ->where('IDENTITY(p.campagne) IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('p.campagne')
            ->getQuery()
            ->getArrayResult();

        $now = new \DateTime();
        // Map des dates de fin par campagne pour calculer le retard
        $dateFins = [];
        foreach ($campagnes as $c) {
            $dateFins[$c->getId()] = $c->getDateFin();
        }

        $result = [];
        foreach ($rows as $row) {
            $cid      = (int) $row['campagne_id'];
            $enRetard = ($dateFins[$cid] ?? null) < $now ? (int) $row['potEnRetard'] : 0;
            $result[$cid] = [
                'totalParticipants' => (int) $row['totalParticipants'],
                'nombreTermines'    => (int) $row['nombreTermines'],
                'nombreEnCours'     => (int) $row['nombreEnCours'],
                'nombreEnRetard'    => $enRetard,
            ];
        }

        // Garantir une entrée pour toutes les campagnes
        foreach ($ids as $id) {
            if (!isset($result[$id])) {
                $result[$id] = [
                    'totalParticipants' => 0,
                    'nombreTermines'    => 0,
                    'nombreEnCours'     => 0,
                    'nombreEnRetard'    => 0,
                ];
            }
        }

        return $result;
    }
}