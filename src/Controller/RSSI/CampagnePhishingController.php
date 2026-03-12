<?php

namespace App\Controller\RSSI;

use App\Entity\CampagnePhishing;
use App\Entity\Departement;
use App\Entity\Employe;
use App\Entity\EnvoiPhishing;
use App\Entity\GabaritPhishing;
use App\Entity\ResultatPhishing;
use App\Entity\RSSI;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    // ================================================================
    // LISTE
    // ================================================================
    #[Route('', name: 'rssi_phishing_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $campagnes = $em->getRepository(CampagnePhishing::class)->findBy(
            ['rssi' => $this->getRssi()],
            ['dateCreation' => 'DESC']
        );

        $gabarits = $em->getRepository(GabaritPhishing::class)->findBy(['estActif' => true]);

        return $this->render('rssi/phishing/liste.html.twig', [
            'campagnes' => $campagnes,
            'gabarits'  => $gabarits,
        ]);
    }

    // ================================================================
    // NOUVELLE CAMPAGNE
    // ================================================================
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
            $datePlanifiee   = $request->request->get('date_planifiee');
            $autorisation    = $request->request->get('autorisation_confirmee');
            $nomAutorisateur = trim($request->request->get('nom_autorisateur', ''));

            if (!$autorisation || empty($nomAutorisateur)) {
                $this->addFlash('error', 'Vous devez confirmer l\'autorisation éthique et saisir le nom du responsable.');
                return $this->render('rssi/phishing/nouvelle.html.twig', compact('gabarits', 'departements'));
            }

            if (empty($titre) || !$gabaritId || empty($cibles) || !$datePlanifiee) {
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
                ->setDatePlanifiee(new \DateTime($datePlanifiee))
                ->setTotalCibles(count($employes))
                ->confirmerAutorisation($nomAutorisateur);

            $em->persist($campagne);

            // ✅ CORRECT : créer EnvoiPhishing + ResultatPhishing liés
            foreach ($employes as $employe) {
                $token = bin2hex(random_bytes(32));

                // ResultatPhishing : comportement de l'employé (tracking)
                $resultat = new ResultatPhishing();
                $resultat->setJetonTrackingUnique($token)
                    ->setCampagne($campagne)
                    ->setEmploye($employe);
                $em->persist($resultat);

                // EnvoiPhishing : acte d'envoi SMTP + lien vers ResultatPhishing
                $envoi = new EnvoiPhishing();
                $envoi->setCampagne($campagne)
                    ->setEmploye($employe)
                    ->setEmailDestinataire($employe->getEmail())
                    ->setSujetUtilise($gabarit->getSujetEmail())
                    ->setDatePlanifiee(new \DateTime($datePlanifiee))
                    ->setStatut('PLANIFIE')
                    ->setResultat($resultat); // ✅ LIEN CRITIQUE
                $em->persist($envoi);
            }

            $em->flush();

            $gabarit->incrementerUtilisations();
            $em->flush();

            $this->addFlash('success', "Campagne « {$titre} » créée avec {$campagne->getTotalCibles()} cible(s).");
            return $this->redirectToRoute('rssi_phishing_detail', ['id' => $campagne->getId()]);
        }

        return $this->render('rssi/phishing/nouvelle.html.twig', compact('gabarits', 'departements'));
    }

    // ================================================================
    // DÉTAIL + STATS
    // ================================================================
    #[Route('/{id}', name: 'rssi_phishing_detail', requirements: ['id' => '\d+'])]
    public function detail(CampagnePhishing $campagne): Response
    {
        $this->verifierAcces($campagne);

        return $this->render('rssi/phishing/detail.html.twig', [
            'campagne' => $campagne,
        ]);
    }

    // ================================================================
    // LANCER LA CAMPAGNE (envoi réel des emails)
    // ================================================================
    #[Route('/{id}/lancer', name: 'rssi_phishing_lancer', methods: ['POST'])]
    public function lancer(
        CampagnePhishing $campagne,
        EntityManagerInterface $em,
        EmailService $emailService,
        UrlGeneratorInterface $router
    ): Response {
        $this->verifierAcces($campagne);

        if ($campagne->getStatut() !== 'PLANIFIEE') {
            $this->addFlash('error', 'Cette campagne ne peut pas être lancée (statut : ' . $campagne->getStatut() . ').');
            return $this->redirectToRoute('rssi_phishing_detail', ['id' => $campagne->getId()]);
        }

        $campagne->setStatut('EN_COURS')->setDateDebut(new \DateTime());
        $em->flush();

        $envois  = $em->getRepository(EnvoiPhishing::class)->findBy([
            'campagne' => $campagne,
            'statut'   => 'PLANIFIE',
        ]);

        $gabarit = $campagne->getGabarit();
        $envoyes = 0;
        $echoues = 0;

        foreach ($envois as $index => $envoi) {
            if ($index > 0) {
                sleep(4); // Latence anti-spam
            }

            // ✅ CORRECT : récupérer le token depuis ResultatPhishing lié
            $resultat = $envoi->getResultat();
            if (!$resultat) {
                error_log("Phishing: ResultatPhishing manquant pour EnvoiPhishing ID {$envoi->getId()}");
                $echoues++;
                continue;
            }

            $token = $resultat->getJetonTrackingUnique();
            if (!$token) {
                error_log("Phishing: token vide pour ResultatPhishing ID {$resultat->getId()}");
                $echoues++;
                continue;
            }

            try {
                // URLs de tracking absolues
                $urlPixel = $router->generate('phishing_track_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                $urlClic  = $router->generate('phishing_track_click', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                // Personnaliser le contenu HTML du gabarit
                $contenu = $gabarit->getContenuHtml();
                $contenu = str_replace(
                    ['{LIEN_PIEGE}', '{{LIEN_PIEGE}}', '{TOKEN}', '{{TOKEN}}',
                     '{PRENOM}', '{NOM}', '{NOM_COMPLET}', '{EMAIL}', '{POSTE}'],
                    [$urlClic, $urlClic, $token, $token,
                     $envoi->getEmploye()->getPrenom(),
                     $envoi->getEmploye()->getNom(),
                     $envoi->getEmploye()->getPrenom() . ' ' . $envoi->getEmploye()->getNom(),
                     $envoi->getEmailDestinataire(),
                     $envoi->getEmploye()->getPoste() ?? 'Employé'],
                    $contenu
                );

                // ── TRACKING MULTI-MÉTHODES (inspiré KnowBe4) ──

                // Méthode 1 : <img> classique — simple mais bloqué par certains clients
                $pixelImg = "<img src=\"{$urlPixel}\" width=\"1\" height=\"1\""
                    . " style=\"display:none;border:0;position:absolute;\" alt=\"\">";

                // Méthode 2 : background-image CSS — contourne le blocage des <img>
                // car chargé par le moteur CSS, pas le filtre d'images
                $pixelCss = "<div style=\"background-image:url('{$urlPixel}');"
                    . "width:1px;height:1px;position:absolute;opacity:0;overflow:hidden;font-size:0;\">"
                    . "</div>";

                // Méthode 3 : <link rel=preload> — chargé par le moteur de rendu
                // avant l'affichage du contenu, très difficile à bloquer
                $pixelPreload = "<link rel=\"preload\" as=\"image\" href=\"{$urlPixel}\">";

                // Injecter preload dans <head> si présent, sinon en haut du body
                if (stripos($contenu, '</head>') !== false) {
                    $contenu = str_ireplace('</head>', $pixelPreload . '</head>', $contenu);
                } else {
                    $contenu = $pixelPreload . $contenu;
                }

                // Injecter img + css en fin de body
                $contenu .= $pixelImg . $pixelCss;

                $emailService->envoyerEmailPhishing(
                    destinataire:    $envoi->getEmailDestinataire(),
                    sujet:           $gabarit->getSujetEmail(),
                    contenuHtml:     $contenu,
                    nomExpediteur:   $gabarit->getNomExpediteur(),
                    emailExpediteur: $gabarit->getEmailExpediteur(),
                    compteEmailDsn:  $gabarit->getCompteEmailDsn()
                );

                // Marquer envoi comme envoyé
                $envoi->marquerCommeEnvoye();

                // Marquer le ResultatPhishing comme email envoyé
                $resultat->setEmailEnvoye(true)->setDateEnvoi(new \DateTime());

                $campagne->incrementerEmailsEnvoyes();
                $envoyes++;

            } catch (\Exception $e) {
                $envoi->marquerCommeEchoue($e->getMessage());
                $echoues++;
                error_log("Phishing envoi échoué [{$envoi->getEmailDestinataire()}] : " . $e->getMessage());
            }

            $em->flush();
        }

        // Terminer la campagne si tous les envois planifiés ont été traités
        if ($echoues === 0 && $envoyes > 0) {
            $campagne->setStatut('TERMINEE')->setDateTermine(new \DateTime());
        } elseif ($envoyes > 0 && $echoues > 0) {
            // Partiellement envoyé : on repasse en PLANIFIEE pour retry possible
            $campagne->setStatut('PLANIFIEE');
        }
        $em->flush();

        if ($envoyes > 0) {
            $this->addFlash('success', "{$envoyes} email(s) phishing envoyé(s) avec succès.");
        }
        if ($echoues > 0) {
            $this->addFlash('warning', "{$echoues} envoi(s) ont échoué. Vérifiez les logs.");
        }

        return $this->redirectToRoute('rssi_phishing_detail', ['id' => $campagne->getId()]);
    }

    // ================================================================
    // ANNULER
    // ================================================================
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

    // ================================================================
    // SUPPRIMER
    // ================================================================
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

    // ================================================================
    // HELPERS
    // ================================================================
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
                    if ($emp && $emp->isEstActif()) {
                        $employes[] = $emp;
                        $ids[]      = $empId;
                    }
                }
            }
        }

        return $employes;
    }
}