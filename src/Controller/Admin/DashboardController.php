<?php

namespace App\Controller\Admin;

use App\Entity\Entreprise;
use App\Entity\Utilisateur;
use App\Entity\ModuleFormation;
use App\Entity\CampagnePhishing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


class DashboardController extends AbstractController
{
     #[Route('/admin/dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        // Stats entreprises
        $totalEntreprises = $em->getRepository(Entreprise::class)->count([]);
        $entreprisesActives = $em->getRepository(Entreprise::class)->countByStatut('ACTIF');
        $entreprisesEnAttente = $em->getRepository(Entreprise::class)->countByStatut('EN_ATTENTE');
        $entreprisesSuspendues = $em->getRepository(Entreprise::class)->countByStatut('SUSPENDU');

        // Stats utilisateurs
        $totalUtilisateurs = $em->getRepository(Utilisateur::class)->count([]);
        $totalRSSI = $em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(Utilisateur::class, 'u')
            ->where('u INSTANCE OF App\Entity\RSSI')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalEmployes = $em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(Utilisateur::class, 'u')
            ->where('u INSTANCE OF App\Entity\Employe')
            ->getQuery()
            ->getSingleScalarResult();

        // Stats modules
        $totalModules = $em->getRepository(ModuleFormation::class)->count([]);
        $modulesPublies = $em->getRepository(ModuleFormation::class)->count(['estPublie' => true]);

        // Stats campagnes phishing
        $totalCampagnesPhishing = $em->getRepository(CampagnePhishing::class)->count([]);
        $campagnesEnCours = $em->getRepository(CampagnePhishing::class)->count(['statut' => 'EN_COURS']);

        // Évolution entreprises (6 derniers mois)
        $evolutionEntreprises = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} months");
            $mois = $date->format('M Y');
            
            $count = $em->createQueryBuilder()
                ->select('COUNT(e.id)')
                ->from(Entreprise::class, 'e')
                ->where('e.dateCreation <= :date')
                ->setParameter('date', $date->modify('last day of this month'))
                ->getQuery()
                ->getSingleScalarResult();
            
            $evolutionEntreprises[] = [
                'mois' => $mois,
                'total' => $count
            ];
        }

        // Entreprises par secteur
        $entreprisesParSecteur = $em->createQueryBuilder()
            ->select('e.secteur, COUNT(e.id) as total')
            ->from(Entreprise::class, 'e')
            ->where('e.statut = :statut')
            ->setParameter('statut', 'ACTIF')
            ->groupBy('e.secteur')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        // Dernières entreprises
        $dernieresEntreprises = $em->getRepository(Entreprise::class)
            ->findBy([], ['dateCreation' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'totalEntreprises' => $totalEntreprises,
                'entreprisesActives' => $entreprisesActives,
                'entreprisesEnAttente' => $entreprisesEnAttente,
                'entreprisesSuspendues' => $entreprisesSuspendues,
                'totalUtilisateurs' => $totalUtilisateurs,
                'totalRSSI' => $totalRSSI,
                'totalEmployes' => $totalEmployes,
                'totalModules' => $totalModules,
                'modulesPublies' => $modulesPublies,
                'totalCampagnesPhishing' => $totalCampagnesPhishing,
                'campagnesEnCours' => $campagnesEnCours,
            ],
            'evolutionEntreprises' => $evolutionEntreprises,
            'entreprisesParSecteur' => $entreprisesParSecteur,
            'dernieresEntreprises' => $dernieresEntreprises,
        ]);
    }
}