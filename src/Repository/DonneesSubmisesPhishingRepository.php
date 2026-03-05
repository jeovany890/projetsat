<?php
namespace App\Repository;
use App\Entity\DonneesSubmisesPhishing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DonneesSubmisesPhishingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, DonneesSubmisesPhishing::class); }
}