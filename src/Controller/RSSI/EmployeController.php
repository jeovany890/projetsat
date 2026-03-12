<?php

namespace App\Controller\RSSI;

use App\Entity\Departement;
use App\Entity\Employe;
use App\Entity\RSSI;
use App\Service\EmailService;
use App\Service\EmailTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

            $departementId   = $request->request->get('departement_defaut');
            $departementDefaut = $departementId ? $em->getRepository(Departement::class)->find($departementId) : null;

            $contenu = file_get_contents($fichier->getPathname());
            $contenu = ltrim($contenu, "\xEF\xBB\xBF"); // Supprimer BOM UTF-8
            $lignes  = array_filter(explode("\n", str_replace("\r\n", "\n", $contenu)));

            $crees = 0; $ignores = 0; $erreurs = [];
            $premiereIgnoree = false;

            foreach ($lignes as $numLigne => $ligne) {
                $ligne = trim($ligne);
                if (empty($ligne)) continue;

                if (!$premiereIgnoree) { $premiereIgnoree = true; continue; }

                $cols = array_map('trim', str_contains($ligne, ';') ? explode(';', $ligne) : explode(',', $ligne));

                if (count($cols) < 3) {
                    $erreurs[] = "Ligne {$numLigne} : format invalide.";
                    $ignores++; continue;
                }

                [$prenom, $nom, $email] = [$cols[0], $cols[1], $cols[2]];
                $telephone = $cols[3] ?? '';
                $poste     = $cols[4] ?? '';

                // Département depuis colonne 5 ou défaut
                $dept = $departementDefaut;
                if (!empty($cols[5])) {
                    $deptNom = $em->getRepository(Departement::class)->findOneBy([
                        'nom' => $cols[5], 'entreprise' => $this->getEntreprise(),
                    ]);
                    if ($deptNom) $dept = $deptNom;
                }

                if (empty($prenom) || empty($nom) || empty($email)) {
                    $erreurs[] = "Ligne {$numLigne} : prénom, nom ou email manquant.";
                    $ignores++; continue;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $erreurs[] = "Ligne {$numLigne} : email invalide ({$email}).";
                    $ignores++; continue;
                }
                if ($em->getRepository(\App\Entity\Utilisateur::class)->findOneBy(['email' => $email])) {
                    $erreurs[] = "Ligne {$numLigne} : {$email} déjà utilisé.";
                    $ignores++; continue;
                }
                if (!$dept) {
                    $erreurs[] = "Ligne {$numLigne} : aucun département pour {$email}.";
                    $ignores++; continue;
                }

                $tempPassword = $this->genererMotDePasse();
                $employe = $this->creerEmploye($prenom, $nom, $email, $telephone, $poste, $dept, $tempPassword, $hasher, $em);
                $crees++;

                // ⏱️ Latence 2s entre chaque email pour éviter blocage Gmail
                if ($crees > 1) {
                    sleep(4);
                }
                $this->envoyerEmailBienvenue($emailService, $employe, $tempPassword);
            }

            $em->flush();

            if ($crees > 0) $this->addFlash('success', "{$crees} employé(s) importé(s) avec succès.");
            if ($ignores > 0) $this->addFlash('warning', "{$ignores} ligne(s) ignorée(s).");
            foreach (array_slice($erreurs, 0, 5) as $err) $this->addFlash('warning', $err);

            return $this->redirectToRoute('rssi_employes_liste');
        }

        return $this->render('rssi/employes/importer.html.twig', ['departements' => $departements]);
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

    // ============================================================
    // HELPERS PRIVÉS
    // ============================================================

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
            ->setRoles(['ROLE_EMPLOYE'])
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