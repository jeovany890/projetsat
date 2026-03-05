<?php
namespace App\Repository;
use App\Entity\SimulationInteractive;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SimulationInteractiveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, SimulationInteractive::class); }
}