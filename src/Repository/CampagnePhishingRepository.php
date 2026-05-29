<?php

namespace App\Repository;

use App\Entity\CampagnePhishing;
use App\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CampagnePhishingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CampagnePhishing::class);
    }

    /**
     * Retourne les campagnes EN_COURS ou TERMINEE d'une entreprise.
     */
    public function findCampagnesActivesEntreprise(Entreprise $entreprise): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.rssi', 'r')
            ->where('r.entreprise = :entreprise')
            ->andWhere('c.statut IN (:statuts)')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statuts', ['EN_COURS', 'TERMINEE'])
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les stats d'une seule campagne en temps réel.
     *
     * @return array{
     *     totalCibles:int,
     *     emailsEnvoyes:int,
     *     liensCliques:int,
     *     emailsSignales:int,
     *     soumissions:int
     * }
     */
 public function getStats(CampagnePhishing $campagne): array
{
    $em = $this->getEntityManager();

    $totalCibles = (int) $em->createQueryBuilder()
        ->select('COUNT(r.id)')
        ->from('App\Entity\ResultatPhishing', 'r')
        ->where('r.campagne = :c')
        ->setParameter('c', $campagne)
        ->getQuery()->getSingleScalarResult();

    $emailsEnvoyes = (int) $em->createQueryBuilder()
        ->select('COUNT(r.id)')
        ->from('App\Entity\ResultatPhishing', 'r')
        ->where('r.campagne = :c')
        ->andWhere('r.statut = :envoye')
        ->setParameter('envoye', 'ENVOYE')
        ->setParameter('c', $campagne)
        ->getQuery()->getSingleScalarResult();

    $liensCliques = (int) $em->createQueryBuilder()
        ->select('COUNT(r.id)')
        ->from('App\Entity\ResultatPhishing', 'r')
        ->where('r.campagne = :c')
        ->andWhere('r.lienClique = true')
        ->setParameter('c', $campagne)
        ->getQuery()->getSingleScalarResult();

    $soumissions = (int) $em->createQueryBuilder()
        ->select('COUNT(r.id)')
        ->from('App\Entity\ResultatPhishing', 'r')
        ->where('r.campagne = :c')
        ->andWhere('r.soumissionDonnees = true')
        ->setParameter('c', $campagne)
        ->getQuery()->getSingleScalarResult();

    $emailsSignales = (int) $em->createQueryBuilder()
        ->select('COUNT(r.id)')
        ->from('App\Entity\ResultatPhishing', 'r')
        ->where('r.campagne = :c')
        ->andWhere('r.signale = true')
        ->setParameter('c', $campagne)
        ->getQuery()->getSingleScalarResult();

    return [
        'totalCibles'    => $totalCibles,
        'emailsEnvoyes'  => $emailsEnvoyes,
        'liensCliques'   => $liensCliques,
        'soumissions'    => $soumissions,
        'emailsSignales' => $emailsSignales,
    ];
}

    /**
     * Calcule les stats de plusieurs campagnes en une seule passe (évite N+1).
     *
     * @param CampagnePhishing[] $campagnes
     * @return array<int, array{
     *     totalCibles:int,
     *     emailsEnvoyes:int,
     *     liensCliques:int,
     *     emailsSignales:int,
     *     soumissions:int
     * }>
     */
   public function getStatsParCampagnes(array $campagnes): array
{
    if (empty($campagnes)) {
        return [];
    }

    $ids = array_map(fn($c) => $c->getId(), $campagnes);
    $em  = $this->getEntityManager();

    // Une seule requête pour tout
    $rows = $em->createQueryBuilder()
        ->select(
            'IDENTITY(r.campagne) AS campagne_id',
            'COUNT(r.id) AS totalCibles',
            'SUM(CASE WHEN r.statut = :envoye THEN 1 ELSE 0 END) AS emailsEnvoyes',
            'SUM(CASE WHEN r.lienClique = true THEN 1 ELSE 0 END) AS liensCliques',
            'SUM(CASE WHEN r.soumissionDonnees = true THEN 1 ELSE 0 END) AS soumissions',
            'SUM(CASE WHEN r.signale = true THEN 1 ELSE 0 END) AS emailsSignales'
        )
        ->from('App\Entity\ResultatPhishing', 'r')
        ->where('IDENTITY(r.campagne) IN (:ids)')
        ->setParameter('ids', $ids)
        ->setParameter('envoye', 'ENVOYE')
        ->groupBy('r.campagne')
        ->getQuery()
        ->getArrayResult();

    $result = [];
    foreach ($rows as $row) {
        $cid = (int)$row['campagne_id'];
        $result[$cid] = [
            'totalCibles'    => (int)$row['totalCibles'],
            'emailsEnvoyes'  => (int)$row['emailsEnvoyes'],
            'liensCliques'   => (int)$row['liensCliques'],
            'soumissions'    => (int)($row['soumissions'] ?? 0),
            'emailsSignales' => (int)($row['emailsSignales'] ?? 0),
        ];
    }

    // Ajouter les campagnes sans résultats
    foreach ($ids as $id) {
        if (!isset($result[$id])) {
            $result[$id] = [
                'totalCibles'    => 0,
                'emailsEnvoyes'  => 0,
                'liensCliques'   => 0,
                'soumissions'    => 0,
                'emailsSignales' => 0,
            ];
        }
    }

    return $result;
}
}