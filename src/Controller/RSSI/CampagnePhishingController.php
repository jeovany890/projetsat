<?php

namespace App\Controller\RSSI;

use App\Entity\CampagnePhishing;
use App\Entity\Departement;
use App\Entity\Employe;
use App\Entity\EnvoiPhishing;
use App\Entity\GabaritPhishing;
use App\Entity\ResultatPhishing;
use App\Entity\RSSI;
use App\Repository\CampagnePhishingRepository;
use App\Service\PhishingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rssi/phishing')]
#[IsGranted('ROLE_RSSI')]
class CampagnePhishingController extends AbstractController
{
    private function getRssi(): RSSI
    {
        /** @var RSSI $rssi */
        return $this->getUser();
    }

    private function getEntreprise(): ?\App\Entity\Entreprise
    {
        return $this->getRssi()->getEntreprise();
    }

    #[Route('', name: 'rssi_phishing_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $campagnes = $em->getRepository(CampagnePhishing::class)->findBy(
            ['rssi' => $this->getRssi()],
            ['dateCreation' => 'DESC']
        );
        /** @var CampagnePhishingRepository $repo */
        $repo     = $em->getRepository(CampagnePhishing::class);
        $gabarits = $em->getRepository(GabaritPhishing::class)->findBy(['estActif' => true]);

        return $this->render('rssi/phishing/liste.html.twig', [
            'campagnes'         => $campagnes,
            'gabarits'          => $gabarits,
            'statsParCampagne'  => $repo->getStatsParCampagnes($campagnes),
        ]);
    }

    #[Route('/nouvelle', name: 'rssi_phishing_nouvelle', methods: ['GET', 'POST'])]
    public function nouvelle(Request $request, EntityManagerInterface $em): Response
    {
        $entreprise   = $this->getEntreprise();
        $gabarits     = $em->getRepository(GabaritPhishing::class)->findBy(['estActif' => true]);
        $departements = $entreprise
            ? $em->getRepository(Departement::class)->findBy(['entreprise' => $entreprise], ['nom' => 'ASC'])
            : [];

        if ($request->isMethod('POST')) {
            $titre           = trim($request->request->get('titre', ''));
            $description     = trim($request->request->get('description', ''));
            $gabaritId       = $request->request->get('gabarit');
            $cibles          = $request->request->all('cibles');
            $autorisation    = $request->request->get('autorisation_confirmee');
            $nomAutorisateur = trim($request->request->get('nom_autorisateur', ''));

            if (!$autorisation || empty($nomAutorisateur)) {
                $this->addFlash('error', 'Vous devez confirmer l\'autorisation éthique et saisir le nom du responsable.');
                return $this->render('rssi/phishing/nouvelle.html.twig', compact('gabarits', 'departements'));
            }
            if (empty($titre) || !$gabaritId || empty($cibles)) {
                $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis.');
                return $this->render('rssi/phishing/nouvelle.html.twig', compact('gabarits', 'departements'));
            }

            $gabarit = $em->getRepository(GabaritPhishing::class)->find($gabaritId);
            if (!$gabarit) {
                $this->addFlash('error', 'Gabarit introuvable.');
                return $this->render('rssi/phishing/nouvelle.html.twig', compact('gabarits', 'departements'));
            }

            $employes = $this->resoudreCibles($cibles, $em, $entreprise);
            if (empty($employes)) {
                $this->addFlash('error', 'Aucun employé sélectionné ou département vide.');
                return $this->render('rssi/phishing/nouvelle.html.twig', compact('gabarits', 'departements'));
            }

            $campagne = new CampagnePhishing();
            $campagne->setTitre($titre)
                ->setDescription($description ?: null)
                ->setGabarit($gabarit)
                ->setRssi($this->getRssi())
                ->setStatut('PLANIFIEE')
                ->confirmerAutorisation($nomAutorisateur);
            $em->persist($campagne);

            foreach ($employes as $employe) {
                $token = bin2hex(random_bytes(32));

                $resultat = new ResultatPhishing();
                $resultat->setJetonTrackingUnique($token)
                    ->setCampagne($campagne)
                    ->setEmploye($employe);
                $em->persist($resultat);

                $envoi = new EnvoiPhishing();
                $envoi->setCampagne($campagne)
                    ->setEmploye($employe)
                    ->setEmailDestinataire($employe->getEmail())
                    ->setSujetUtilise($gabarit->getSujetEmail())
                    ->setDatePlanifiee(new \DateTime())
                    ->setStatut('PLANIFIE');
                $em->persist($envoi);

                // ResultatPhishing est le propriétaire de la FK envoi_id
                $resultat->setEnvoi($envoi);
            }

            $em->flush();
            $gabarit->incrementerUtilisations();
            $em->flush();

            $nbCibles = count($employes);
            $this->addFlash('success', "Campagne « {$titre} » créée avec {$nbCibles} cible(s).");
            return $this->redirectToRoute('rssi_phishing_detail', ['id' => $campagne->getId()]);
        }

        return $this->render('rssi/phishing/nouvelle.html.twig', compact('gabarits', 'departements'));
    }

    #[Route('/{id}', name: 'rssi_phishing_detail', requirements: ['id' => '\d+'])]
    public function detail(CampagnePhishing $campagne, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($campagne);
        /** @var CampagnePhishingRepository $repo */
        $repo = $em->getRepository(CampagnePhishing::class);

        return $this->render('rssi/phishing/detail.html.twig', [
            'campagne' => $campagne,
            'stats'    => $repo->getStats($campagne),
        ]);
    }

  #[Route('/{id}/lancer', name: 'rssi_phishing_lancer', methods: ['POST'])]
public function lancer(
    CampagnePhishing $campagne,
    EntityManagerInterface $em,
    PhishingService $phishingService
): Response {
    $this->verifierAcces($campagne);
    if ($campagne->getStatut() !== 'PLANIFIEE') {
        $this->addFlash('error', 'Cette campagne ne peut pas être lancée (statut : ' . $campagne->getStatut() . ').');
        return $this->redirectToRoute('rssi_phishing_detail', ['id' => $campagne->getId()]);
    }

    $result = $phishingService->lancerCampagne($campagne);
    $em->flush();

    if ($result['envoyes'] > 0) {
        $this->addFlash('success', "{$result['envoyes']} email(s) phishing envoyé(s) avec succès.");
    }
    if ($result['echoues'] > 0) {
        $erreur = implode(' ; ', array_unique($result['erreurs']));
        $this->addFlash('warning', "{$result['echoues']} envoi(s) ont échoué. Erreurs : {$erreur}");
    }

    return $this->redirectToRoute('rssi_phishing_detail', ['id' => $campagne->getId()]);
}
    #[Route('/{id}/annuler', name: 'rssi_phishing_annuler', methods: ['POST'])]
    public function annuler(CampagnePhishing $campagne, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($campagne);
        if (in_array($campagne->getStatut(), ['PLANIFIEE', 'EN_COURS'])) {
            $campagne->setStatut('ANNULEE');
            $em->flush();
            $this->addFlash('success', 'Campagne annulée.');
        }
        return $this->redirectToRoute('rssi_phishing_detail', ['id' => $campagne->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'rssi_phishing_supprimer', methods: ['POST'])]
    public function supprimer(CampagnePhishing $campagne, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($campagne);
        $titre = $campagne->getTitre();
        $em->remove($campagne);
        $em->flush();
        $this->addFlash('success', "Campagne « {$titre} » supprimée.");
        return $this->redirectToRoute('rssi_phishing_liste');
    }

    private function verifierAcces(CampagnePhishing $campagne): void
    {
        if ($campagne->getRssi()->getId() !== $this->getRssi()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function resoudreCibles(array $cibles, EntityManagerInterface $em, ?\App\Entity\Entreprise $entreprise): array
    {
        $employes = [];
        $ids      = [];

        foreach ($cibles as $cible) {
            if (str_starts_with($cible, 'dept_')) {
                $deptId = (int) substr($cible, 5);
                $dept   = $em->getRepository(Departement::class)->find($deptId);
                if ($dept && $dept->getEntreprise() === $entreprise) {
                    foreach ($dept->getEmployes() as $e) {
                        if ($e->isEstActif() && !in_array($e->getId(), $ids)) {
                            $employes[] = $e;
                            $ids[]      = $e->getId();
                        }
                    }
                }
            } else {
                $empId = (int) $cible;
                if (!in_array($empId, $ids)) {
                    $emp = $em->getRepository(Employe::class)->find($empId);
                    // Garde sécurité : vérifier que l'employé appartient bien à l'entreprise du RSSI
                    // Un employé sans département n'appartient à aucune entreprise — on l'exclut
                    if ($emp && $emp->isEstActif() && $emp->getEntreprise() === $entreprise) {
                        $employes[] = $emp;
                        $ids[]      = $emp->getId();
                    }
                }
            }

        }
        

        return $employes;
    }
    #[Route('/{id}/terminer', name: 'rssi_phishing_terminer', methods: ['POST'])]
public function terminer(CampagnePhishing $campagne, EntityManagerInterface $em): Response
{
    $this->verifierAcces($campagne);
    if ($campagne->getStatut() === 'EN_COURS') {
        $campagne->setStatut('TERMINEE')->setDateTermine(new \DateTime());
        $em->flush();
        $this->addFlash('success', 'Campagne marquée comme terminée.');
    } else {
        $this->addFlash('warning', 'Seules les campagnes en cours peuvent être terminées.');
    }
    return $this->redirectToRoute('rssi_phishing_detail', ['id' => $campagne->getId()]);
}
}