<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\ModuleFormation;
use App\Entity\ProgressionModule;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Moteur central de scoring comportemental.
 *
 * Toute modification du score de vigilance ou des points passe par ici.
 * Les controllers ne touchent plus directement ajusterScoreVigilance()
 * ni ajouterPoints() — ils appellent ce service.
 *
 * Règles de vigilance :
 * ┌─────────────────────────┬───────────┐
 * │ Événement               │ Vigilance │
 * ├─────────────────────────┼───────────┤
 * │ Clic sur lien phishing       │   -15     │
 * │ Soumission identifiants      │   -25     │
 * │ Téléchargement fichier suspect│   -20     │
 * │ Signalement correct phishing  │   +10     │
 * │ Aucune action sur email suspect│   +3     │
 * │ Faux positif sur email légitime│   -3      │
 * │ Formation terminée           │   +5      │
 * └─────────────────────────┴───────────┘
 */
class ScoringMoteurService
{
    // ── Constantes d'événements ──────────────────────────────────
    public const EVT_PHISHING_CLIC        = 'PHISHING_CLIC';
    public const EVT_PHISHING_SOUMISSION  = 'PHISHING_SOUMISSION';
    public const EVT_PHISHING_SIGNALEMENT = 'PHISHING_SIGNALEMENT';
    public const EVT_PHISHING_IGNORE         = 'PHISHING_IGNORE';
    public const EVT_PHISHING_FAUX_POSITIF    = 'PHISHING_FAUX_POSITIF';
    public const EVT_FORMATION_TERMINEE       = 'FORMATION_TERMINEE';
    public const EVT_QUIZ_REUSSI              = 'QUIZ_REUSSI';
    public const EVT_QUIZ_ECHOUE              = 'QUIZ_ECHOUE';

    // ── Table de pondération ──────────────────────────────────────
    private const PONDERATION = [
        self::EVT_PHISHING_CLIC          => ['vigilance' => -15],
        self::EVT_PHISHING_SOUMISSION    => ['vigilance' => -25],
        self::EVT_PHISHING_SIGNALEMENT   => ['vigilance' => +10],
        self::EVT_PHISHING_IGNORE        => ['vigilance' =>  +3],
        self::EVT_PHISHING_FAUX_POSITIF  => ['vigilance' =>  -3],
        self::EVT_FORMATION_TERMINEE     => ['vigilance' =>  +5],
        self::EVT_QUIZ_REUSSI            => ['vigilance' =>  +3],
        self::EVT_QUIZ_ECHOUE            => ['vigilance' =>   0],
    ];

    public function __construct(private EntityManagerInterface $em) {}

    // ─────────────────────────────────────────────────────────────
    // Méthode principale : traiter un événement
    // ─────────────────────────────────────────────────────────────

    /**
     * Applique les pondérations d'un événement sur l'employé.
     * Cherche automatiquement un module de formation à proposer
     * si l'événement est un échec phishing et qu'une catégorie est fournie.
     *
     * @param string      $typeEvenement    Constante EVT_*
     * @param string|null $categoriePhishing Catégorie du gabarit phishing (ex: 'banque')
     *
     * @return ScoringResultat DTO avec les deltas et le module proposé
     */
    public function traiterEvenement(
        Employe $employe,
        string  $typeEvenement,
        ?string $categoriePhishing = null
    ): ScoringResultat {

        $poids = self::PONDERATION[$typeEvenement] ?? ['vigilance' => 0];

        $deltaVigilance = $poids['vigilance'];
        $deltaPoints    = 0;

        // Appliquer les changements de vigilance uniquement.
        if ($deltaVigilance !== 0) {
            $employe->ajusterScoreVigilance($deltaVigilance);
        }

        // Chercher un module à proposer si c'est un échec phishing
        $modulePropose = null;
        $estEchecPhishing = in_array($typeEvenement, [
            self::EVT_PHISHING_CLIC,
            self::EVT_PHISHING_SOUMISSION,
        ]);

        if ($estEchecPhishing && $categoriePhishing) {
            $modulePropose = $this->trouverModuleRecommandePour($employe, $categoriePhishing);
        }

        return new ScoringResultat(
            nouveauScore:    $employe->getScoreVigilance(),
            nouveauxPoints:  $employe->getTotalPoints(),
            deltaVigilance:  $deltaVigilance,
            deltaPoints:     $deltaPoints,
            modulePropose:   $modulePropose
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Méthodes de commodité (raccourcis pour les controllers)
    // ─────────────────────────────────────────────────────────────

    public function phishingClic(Employe $employe, string $categorieGabarit): ScoringResultat
    {
        return $this->traiterEvenement($employe, self::EVT_PHISHING_CLIC, $categorieGabarit);
    }

    public function phishingSoumission(Employe $employe, string $categorieGabarit): ScoringResultat
    {
        return $this->traiterEvenement($employe, self::EVT_PHISHING_SOUMISSION, $categorieGabarit);
    }

    public function phishingSignalement(Employe $employe): ScoringResultat
    {
        return $this->traiterEvenement($employe, self::EVT_PHISHING_SIGNALEMENT);
    }

    public function phishingIgnore(Employe $employe): ScoringResultat
    {
        return $this->traiterEvenement($employe, self::EVT_PHISHING_IGNORE);
    }

    public function phishingFauxPositif(Employe $employe): ScoringResultat
    {
        return $this->traiterEvenement($employe, self::EVT_PHISHING_FAUX_POSITIF);
    }

    public function formationTerminee(Employe $employe): ScoringResultat
    {
        // Vigilance : +5 fixe (défini dans PONDERATION)
        $employe->ajusterScoreVigilance(+5);

        return new ScoringResultat(
            nouveauScore:   $employe->getScoreVigilance(),
            nouveauxPoints: $employe->getTotalPoints(),
            deltaVigilance: +5,
            deltaPoints:    0,
            modulePropose:  null,
        );
    }

    public function quizReussi(Employe $employe): ScoringResultat
    {
        return $this->traiterEvenement($employe, self::EVT_QUIZ_REUSSI);
    }

    public function quizEchoue(Employe $employe): ScoringResultat
    {
        return $this->traiterEvenement($employe, self::EVT_QUIZ_ECHOUE);
    }

    // ─────────────────────────────────────────────────────────────
    // Logique de recommandation de formation
    // ─────────────────────────────────────────────────────────────

    /**
     * Cherche le premier module publié de la catégorie donnée
     * que l'employé n'a pas encore commencé.
     */
    public function trouverModuleRecommandePour(Employe $employe, string $categorie): ?ModuleFormation
    {
        $modules = $this->em->getRepository(ModuleFormation::class)->findBy([
            'categorie' => $categorie,
            'estPublie'  => true,
        ]);

        foreach ($modules as $module) {
            $dejaAssigne = $this->em->getRepository(ProgressionModule::class)->findOneBy([
                'employe' => $employe,
                'module'  => $module,
            ]);
            if (!$dejaAssigne) {
                return $module;
            }
        }

        return null;
    }
}

// ─────────────────────────────────────────────────────────────────
// DTO ScoringResultat
// Retourné par chaque appel au moteur.
// Utilisé par les controllers pour passer les données aux templates.
// ─────────────────────────────────────────────────────────────────

final class ScoringResultat
{
    public function __construct(
        public readonly float           $nouveauScore,
        public readonly int             $nouveauxPoints,
        public readonly int             $deltaVigilance,
        public readonly int             $deltaPoints,
        public readonly ?ModuleFormation $modulePropose,
    ) {}

    public function aUnModulePropose(): bool
    {
        return $this->modulePropose !== null;
    }
}