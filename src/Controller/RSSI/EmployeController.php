<?php

namespace App\Controller\RSSI;

use App\Entity\Departement;
use App\Entity\Employe;
use App\Entity\ProgressionModule;
use App\Entity\ResultatPhishing;
use App\Entity\ResultatSimulation;
use App\Entity\RSSI;
use App\Entity\SignalementPhishing;
use App\Entity\TentativeQuiz;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use App\Service\RapportPdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rssi/employes')]
#[IsGranted('ROLE_RSSI')]
class EmployeController extends AbstractController
{
    private function getRssi(): RSSI
    {
        return $this->getUser();
    }

    private function getEntreprise(): ?\App\Entity\Entreprise
    {
        return $this->getRssi()->getEntreprise();
    }

    private function getDepartements(EntityManagerInterface $em): array
    {
        $entreprise = $this->getEntreprise();
        if (!$entreprise) return [];
        return $em->getRepository(Departement::class)
            ->findBy(['entreprise' => $entreprise], ['nom' => 'ASC']);
    }

    #[Route('', name: 'rssi_employes_liste')]
    public function liste(EntityManagerInterface $em): Response
    {
        $entreprise = $this->getEntreprise();
        $employes = $entreprise ? $em->createQueryBuilder()
            ->select('e')->from(Employe::class, 'e')
            ->join('e.departement', 'd')
            ->where('d.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()->getResult() : [];

        return $this->render('rssi/employes/liste.html.twig', [
            'employes'     => $employes,
            'departements' => $this->getDepartements($em),
        ]);
    }

    #[Route('/nouveau', name: 'rssi_employes_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        EmailService $emailService
    ): Response {
        $departements = $this->getDepartements($em);

        if ($request->isMethod('POST')) {
            $prenom        = trim($request->request->get('prenom', ''));
            $nom           = trim($request->request->get('nom', ''));
            $email         = trim($request->request->get('email', ''));
            $telephone     = trim($request->request->get('telephone', ''));
            $poste         = trim($request->request->get('poste', ''));
            $departementId = $request->request->get('departement');

            if (empty($prenom) || empty($nom) || empty($email) || empty($departementId)) {
                $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis.');
                return $this->render('rssi/employes/nouveau.html.twig', ['departements' => $departements]);
            }

            $existant = $em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => $email]);
            if ($existant) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->render('rssi/employes/nouveau.html.twig', ['departements' => $departements]);
            }

            $departement = $em->getRepository(Departement::class)->find($departementId);
            if (!$departement || $departement->getEntreprise() !== $this->getEntreprise()) {
                $this->addFlash('error', 'Département invalide.');
                return $this->render('rssi/employes/nouveau.html.twig', ['departements' => $departements]);
            }

            $tempPassword = $this->genererMotDePasse();
            $employe = $this->creerEmploye($prenom, $nom, $email, $telephone, $poste, $departement, $tempPassword, $hasher, $em);
            $em->flush();

            $this->envoyerEmailBienvenue($emailService, $employe, $tempPassword);

            $this->addFlash('success', "Employé {$prenom} {$nom} créé. Email envoyé à {$email}.");
            return $this->redirectToRoute('rssi_employes_liste');
        }

        return $this->render('rssi/employes/nouveau.html.twig', ['departements' => $departements]);
    }

    #[Route('/importer', name: 'rssi_employes_importer', methods: ['GET', 'POST'])]
    public function importer(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        EmailService $emailService
    ): Response {
        $departements = $this->getDepartements($em);

        if ($request->isMethod('POST')) {
            $fichier = $request->files->get('fichier_csv');
            if (!$fichier) {
                $this->addFlash('error', 'Veuillez sélectionner un fichier CSV.');
                return $this->render('rssi/employes/importer.html.twig', ['departements' => $departements]);
            }

            if (!$this->isCsvFileValid($fichier)) {
                $this->addFlash('error', 'Le fichier doit être un CSV valide.');
                return $this->render('rssi/employes/importer.html.twig', ['departements' => $departements]);
            }

            $departementId    = $request->request->get('departement_defaut');
            $departementDefaut = $departementId ? $em->getRepository(Departement::class)->find($departementId) : null;

            $contenu = file_get_contents($fichier->getPathname());
            $contenu = ltrim($contenu, "\xEF\xBB\xBF");
            $lignes  = array_filter(explode("\n", str_replace("\r\n", "\n", $contenu)));

            $crees = 0; $ignores = 0; $erreurs = [];
            $premiereIgnoree = false;

            foreach ($lignes as $numLigne => $ligne) {
                $ligne = trim($ligne);
                if (empty($ligne)) continue;
                if (!$premiereIgnoree) { $premiereIgnoree = true; continue; }

                $cols = array_map('trim', str_contains($ligne, ';') ? explode(';', $ligne) : explode(',', $ligne));
                if (count($cols) < 3) { $erreurs[] = "Ligne {$numLigne} : format invalide."; $ignores++; continue; }

                [$prenom, $nom, $email] = [$cols[0], $cols[1], $cols[2]];
                $telephone = $cols[3] ?? '';
                $poste     = $cols[4] ?? '';
                $dept = $departementDefaut;
                if (!empty($cols[5])) {
                    $deptNom = $em->getRepository(Departement::class)->findOneBy(['nom' => $cols[5], 'entreprise' => $this->getEntreprise()]);
                    if ($deptNom) $dept = $deptNom;
                }

                if (empty($prenom) || empty($nom) || empty($email)) { $erreurs[] = "Ligne {$numLigne} : prénom, nom ou email manquant."; $ignores++; continue; }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erreurs[] = "Ligne {$numLigne} : email invalide ({$email})."; $ignores++; continue; }
                if ($em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => $email])) { $erreurs[] = "Ligne {$numLigne} : {$email} déjà utilisé."; $ignores++; continue; }
                if (!$dept) { $erreurs[] = "Ligne {$numLigne} : aucun département pour {$email}."; $ignores++; continue; }

                $tempPassword = $this->genererMotDePasse();
                $employe = $this->creerEmploye($prenom, $nom, $email, $telephone, $poste, $dept, $tempPassword, $hasher, $em);
                $crees++;
                if ($crees > 1) sleep(4);
                $this->envoyerEmailBienvenue($emailService, $employe, $tempPassword);
            }

            $em->flush();
            if ($crees > 0)   $this->addFlash('success', "{$crees} employé(s) importé(s) avec succès.");
            if ($ignores > 0) $this->addFlash('warning', "{$ignores} ligne(s) ignorée(s).");
            foreach (array_slice($erreurs, 0, 5) as $err) $this->addFlash('warning', $err);
            return $this->redirectToRoute('rssi_employes_liste');
        }

        return $this->render('rssi/employes/importer.html.twig', ['departements' => $departements]);
    }

    private function isCsvFileValid(UploadedFile $file): bool
    {
        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'application/octet-stream',
            'text/comma-separated-values',
        ];

        $mimeType = $file->getClientMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($mimeType, $allowedMimeTypes, true) && $extension === 'csv';
    }

    #[Route('/{id}', name: 'rssi_employes_detail', requirements: ['id' => '\d+'])]
    public function detail(Employe $employe): Response
    {
        $this->verifierAcces($employe);
        return $this->render('rssi/employes/detail.html.twig', ['employe' => $employe]);
    }

    #[Route('/{id}/modifier', name: 'rssi_employes_modifier', methods: ['GET', 'POST'])]
    public function modifier(Employe $employe, Request $request, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($employe);
        $departements = $this->getDepartements($em);

        if ($request->isMethod('POST')) {
            $employe->setPrenom(trim($request->request->get('prenom', '')));
            $employe->setNom(trim($request->request->get('nom', '')));
            $employe->setTelephone(trim($request->request->get('telephone', '')) ?: null);
            $employe->setPoste(trim($request->request->get('poste', '')) ?: null);

            $dept = $em->getRepository(Departement::class)->find($request->request->get('departement'));
            if ($dept && $dept->getEntreprise() === $this->getEntreprise()) {
                $employe->setDepartement($dept);
            }

            $em->flush();
            $this->addFlash('success', 'Employé modifié avec succès.');
            return $this->redirectToRoute('rssi_employes_liste');
        }

        return $this->render('rssi/employes/modifier.html.twig', [
            'employe' => $employe, 'departements' => $departements,
        ]);
    }

    #[Route('/{id}/activer', name: 'rssi_employes_activer', methods: ['POST'])]
    public function activer(Employe $employe, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($employe);
        $employe->setEstActif(!$employe->isEstActif());
        $em->flush();
        $statut = $employe->isEstActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Employé {$statut}.");
        return $this->redirectToRoute('rssi_employes_liste');
    }

    #[Route('/{id}/supprimer', name: 'rssi_employes_supprimer', methods: ['POST'])]
    public function supprimer(Employe $employe, EntityManagerInterface $em): Response
    {
        $this->verifierAcces($employe);
        $nom = $employe->getNomComplet();
        $em->remove($employe);
        $em->flush();
        $this->addFlash('success', "Employé {$nom} supprimé.");
        return $this->redirectToRoute('rssi_employes_liste');
    }

    // ═══════════════════════════════════════════════
    // RAPPORT PDF — NOUVELLE FONCTIONNALITÉ
    // ═══════════════════════════════════════════════

    #[Route('/{id}/rapport-pdf', name: 'rssi_employes_rapport_pdf', requirements: ['id' => '\d+'])]
    public function rapportPdf(
        Employe $employe,
        EntityManagerInterface $em,
        RapportPdfService $rapportPdfService
    ): Response {
        $this->verifierAcces($employe);

        // 1. Résultats phishing
        $resultatsPhishing = $em->getRepository(ResultatPhishing::class)
            ->findBy(['employe' => $employe], ['dateEnvoi' => 'DESC']);

        $nbClics = 0;
        foreach ($resultatsPhishing as $r) {
            if ($r->isLienClique()) $nbClics++;
        }

        // 2. Formations / progressions
        $progressions = $em->getRepository(ProgressionModule::class)
            ->findBy(['employe' => $employe], ['dateDebut' => 'DESC']);

        $modulesTermines = count(array_filter(
            $progressions,
            fn($p) => $p->getStatut() === 'TERMINE'
        ));

        // 3. Tentatives quiz
        $tentativesQuiz = $em->getRepository(TentativeQuiz::class)
            ->findBy(['employe' => $employe], ['dateTermine' => 'DESC']);

        $scoreQuizMoyen = 0;
        if (count($tentativesQuiz) > 0) {
            $scoreQuizMoyen = array_sum(array_map(fn($t) => $t->getScore(), $tentativesQuiz)) / count($tentativesQuiz);
        }

        // 4. Simulations
        $simulations = $em->getRepository(ResultatSimulation::class)
            ->findBy(['employe' => $employe], ['dateTermine' => 'DESC']);

        // 5. Signalements manuels
        $signalements = $em->getRepository(SignalementPhishing::class)
            ->findBy(['employe' => $employe], ['dateSignalement' => 'DESC']);

        $nbSignalements = count($signalements);

        // 6. Score global
        $scoreVigilance = $employe->getScoreVigilance();

        // 7. Niveau de risque
        if ($scoreVigilance >= 75) {
            $niveauRisque  = 'faible';
            $niveauLabel   = 'Risque faible';
        } elseif ($scoreVigilance >= 50) {
            $niveauRisque  = 'moyen';
            $niveauLabel   = 'Risque moyen';
        } else {
            $niveauRisque  = 'eleve';
            $niveauLabel   = 'Risque élevé';
        }

        // 8. Analyse comportementale
        $analyses = [];
        if ($nbClics > 2) {
            $analyses[] = "L'employé a cliqué à plusieurs reprises ({$nbClics} fois) sur des liens de phishing — comportement à risque élevé.";
        } elseif ($nbClics === 1) {
            $analyses[] = "L'employé a cliqué une fois sur un lien de phishing — vigilance insuffisante.";
        } else {
            $analyses[] = "Aucun clic sur un lien de phishing détecté — bon signe de vigilance passive.";
        }

        if ($nbSignalements === 0) {
            $analyses[] = "Aucune tentative de signalement observée. L'employé ne signale pas activement les menaces.";
        } else {
            $analyses[] = "L'employé a effectué {$nbSignalements} signalement(s) — comportement proactif face aux menaces.";
        }

        if ($modulesTermines >= 3) {
            $analyses[] = "Bonne assiduité en formation : {$modulesTermines} module(s) complété(s).";
        } elseif ($modulesTermines === 0) {
            $analyses[] = "Aucune formation complétée. Une montée en compétence est fortement recommandée.";
        }

        // 9. Recommandations
        $recommandations = [];
        if ($nbClics > 0) {
            $recommandations[] = "Suivre une formation spécifique sur la détection du phishing par email.";
            $recommandations[] = "Sensibilisation aux techniques d'ingénierie sociale (urgence, autorité, imitation de marques).";
        }
        if ($nbSignalements === 0) {
            $recommandations[] = "Encourager l'utilisation de l'outil de signalement pour toute menace suspecte.";
        }
        if ($modulesTermines < 2) {
            $recommandations[] = "Compléter au moins 2 modules de formation sur la cybersécurité.";
            $recommandations[] = "Prioriser les modules : Phishing, Mots de passe et Ingénierie sociale.";
        }
        if ($scoreVigilance < 50) {
            $recommandations[] = "Envisager une session de sensibilisation individuelle avec le RSSI.";
        }
        if (empty($recommandations)) {
            $recommandations[] = "Maintenir les bonnes pratiques actuelles de cybersécurité.";
            $recommandations[] = "Continuer les formations pour rester informé des nouvelles menaces.";
        }

        // 10. Conclusion
        if ($niveauRisque === 'faible') {
            $conclusion = "L'employé présente un excellent niveau de vigilance face aux cybermenaces. Son comportement reflète une bonne assimilation des pratiques de sécurité. Il est recommandé de maintenir cet engagement par une formation continue.";
        } elseif ($niveauRisque === 'moyen') {
            $conclusion = "L'employé présente un niveau de vigilance moyen. Des lacunes ont été identifiées, notamment en matière de détection des emails de phishing. Une formation complémentaire est recommandée afin de réduire les risques liés aux attaques.";
        } else {
            $conclusion = "L'employé présente un niveau de risque élevé. Des comportements à risque ont été observés lors des simulations. Une action corrective immédiate est fortement recommandée : formation obligatoire et suivi renforcé par le RSSI.";
        }

        return $rapportPdfService->generer([
            'employe'           => $employe,
            'dateRapport'       => new \DateTime(),
            'scoreVigilance'    => $scoreVigilance,
            'niveauRisque'      => $niveauRisque,
            'niveauLabel'       => $niveauLabel,
            'nbCampagnes'       => count($resultatsPhishing),
            'nbClics'           => $nbClics,
            'nbSignalements'    => $nbSignalements,
            'resultatsPhishing' => $resultatsPhishing,
            'progressions'      => $progressions,
            'modulesTermines'   => $modulesTermines,
            'tentativesQuiz'    => $tentativesQuiz,
            'scoreQuizMoyen'    => round($scoreQuizMoyen),
            'simulations'       => $simulations,
            'analyses'          => $analyses,
            'recommandations'   => $recommandations,
            'conclusion'        => $conclusion,
        ]);
    }

    // ═══════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════

    private function verifierAcces(Employe $employe): void
    {
        $entreprise = $this->getEntreprise();
        if (!$entreprise || $employe->getEntreprise()?->getId() !== $entreprise->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function genererMotDePasse(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#!';
        $mdp = '';
        for ($i = 0; $i < 10; $i++) {
            $mdp .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $mdp;
    }

    private function creerEmploye(
        string $prenom, string $nom, string $email,
        string $telephone, string $poste,
        Departement $departement, string $tempPassword,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Employe {
        $employe = new Employe();
        $employe->setPrenom($prenom)->setNom($nom)->setEmail($email)
            ->setTelephone($telephone ?: null)->setPoste($poste ?: null)
            ->setDepartement($departement)->setEstActif(true)
            ->setEstVerifie(true)->setEstPremiereConnexion(true)
            ->setMotDePasse($hasher->hashPassword($employe, $tempPassword));
        $em->persist($employe);
        return $employe;
    }

    private function envoyerEmailBienvenue(EmailService $emailService, Employe $employe, string $tempPassword): void
    {
        $prenom     = $employe->getPrenom();
        $nom        = $employe->getNom();
        $email      = $employe->getEmail();
        $entreprise = $employe->getEntreprise()?->getNom() ?? 'votre entreprise';
        $dept       = $employe->getDepartement()?->getNom() ?? '';

        try {
            $emailService->envoyerEmailLeitime(
                $email,
                'Bienvenue sur SAT Platform — Vos identifiants de connexion',
                EmailTemplateService::bienvenueEmploye(
                    $prenom, $nom, $email, $tempPassword,
                    $entreprise, $dept,
                    'http://localhost:8000/login'
                )
            );
        } catch (\Exception $e) {
            error_log("Erreur email employé {$email} : " . $e->getMessage());
        }
    }
}