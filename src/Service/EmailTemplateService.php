<?php

namespace App\Service;

/**
 * Tous les templates email de SAT Platform.
 * Design épuré, typographie soignée, compatible tous clients email.
 * Inspiré des standards Anthropic / Stripe / Linear.
 */
class EmailTemplateService
{
    // ─────────────────────────────────────────
    // WRAPPER GLOBAL
    // ─────────────────────────────────────────

    private static function wrap(
        string $contenu,
        string $sujetLabel = '',
        string $accentColor = '#0EA5E9'
    ): string {
        return "
<!DOCTYPE html>
<html lang='fr'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>SAT Platform</title>
</head>
<body style='margin:0;padding:0;background:#F8FAFC;font-family:\"Helvetica Neue\",Helvetica,Arial,sans-serif;'>

<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#F8FAFC;'>
<tr><td align='center' style='padding:40px 16px;'>

  <table role='presentation' width='560' cellpadding='0' cellspacing='0' style='max-width:560px;width:100%;'>

    <!-- HEADER -->
    <tr>
      <td style='padding-bottom:28px;text-align:center;'>
        <table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 auto;'>
          <tr>
            <td style='background:{$accentColor};border-radius:12px;padding:10px 14px;display:inline-block;vertical-align:middle;'>
              <span style='font-size:16px;font-weight:800;color:#FFFFFF;letter-spacing:-0.02em;'>SAT</span>
            </td>
            <td style='padding-left:10px;vertical-align:middle;'>
              <span style='font-size:15px;font-weight:600;color:#0F172A;letter-spacing:-0.01em;'>Platform</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- CARD -->
    <tr>
      <td style='background:#FFFFFF;border-radius:16px;border:1px solid #E2E8F0;overflow:hidden;'>

        <!-- Barre accent top -->
        <tr>
          <td style='height:3px;background:linear-gradient(90deg,{$accentColor},{$accentColor}CC);'></td>
        </tr>

        <!-- Contenu -->
        <tr>
          <td style='padding:40px 44px 36px;'>
            {$contenu}
          </td>
        </tr>

      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style='padding:28px 0 8px;text-align:center;'>
        <p style='margin:0 0 6px;font-size:12px;color:#94A3B8;line-height:1.5;'>
          SAT Platform &mdash; Sensibilisation à la Cybersécurité pour les PME du Bénin
        </p>
        <p style='margin:0;font-size:11px;color:#CBD5E1;'>
          Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.
        </p>
      </td>
    </tr>

  </table>
</td></tr>
</table>

</body>
</html>";
    }

    // ─────────────────────────────────────────
    // COMPOSANTS RÉUTILISABLES
    // ─────────────────────────────────────────

    private static function h1(string $texte): string
    {
        return "<h1 style='margin:0 0 8px;font-size:24px;font-weight:700;color:#0F172A;letter-spacing:-0.03em;line-height:1.2;'>{$texte}</h1>";
    }

    private static function h2(string $texte): string
    {
        return "<h2 style='margin:0 0 6px;font-size:18px;font-weight:600;color:#0F172A;letter-spacing:-0.02em;'>{$texte}</h2>";
    }

    private static function p(string $texte, string $style = ''): string
    {
        return "<p style='margin:0 0 16px;font-size:15px;color:#475569;line-height:1.65;{$style}'>{$texte}</p>";
    }

    private static function divider(): string
    {
        return "<hr style='border:none;border-top:1px solid #F1F5F9;margin:28px 0;'>";
    }

    private static function btn(string $texte, string $url, string $color = '#0EA5E9'): string
    {
        return "
        <table role='presentation' cellpadding='0' cellspacing='0' style='margin:24px 0;'>
          <tr>
            <td style='background:{$color};border-radius:10px;'>
              <a href='{$url}' style='display:inline-block;padding:14px 32px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;letter-spacing:0.01em;'>{$texte}</a>
            </td>
          </tr>
        </table>";
    }

    private static function infoBox(string $contenu, string $bg = '#F0F9FF', string $border = '#BAE6FD', string $text = '#0369A1'): string
    {
        return "
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin:20px 0;'>
          <tr>
            <td style='background:{$bg};border:1px solid {$border};border-radius:10px;padding:16px 20px;font-size:14px;color:{$text};line-height:1.6;'>
              {$contenu}
            </td>
          </tr>
        </table>";
    }

    private static function warningBox(string $contenu): string
    {
        return self::infoBox($contenu, '#FFFBEB', '#FDE68A', '#92400E');
    }

    private static function successBox(string $contenu): string
    {
        return self::infoBox($contenu, '#F0FDF4', '#BBF7D0', '#166534');
    }

    private static function dataRow(string $label, string $valeur): string
    {
        return "
        <tr>
          <td style='padding:8px 0;border-bottom:1px solid #F8FAFC;vertical-align:top;'>
            <span style='font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#94A3B8;'>{$label}</span>
          </td>
          <td style='padding:8px 0 8px 16px;border-bottom:1px solid #F8FAFC;vertical-align:top;text-align:right;'>
            <span style='font-size:14px;font-weight:500;color:#1E293B;'>{$valeur}</span>
          </td>
        </tr>";
    }

    private static function dataTable(array $rows): string
    {
        $html = "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin:20px 0;'>";
        foreach ($rows as [$label, $val]) {
            $html .= self::dataRow($label, $val);
        }
        $html .= "</table>";
        return $html;
    }

    private static function badge(string $texte, string $bg = '#DBEAFE', string $color = '#1D4ED8'): string
    {
        return "<span style='display:inline-block;background:{$bg};color:{$color};font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;padding:3px 10px;border-radius:20px;'>{$texte}</span>";
    }

    private static function greeting(string $prenom, string $nom = ''): string
    {
        $nom = $nom ? " {$nom}" : '';
        return self::p("Bonjour <strong style='color:#1E293B;'>{$prenom}{$nom}</strong>,");
    }

    private static function signature(): string
    {
        return "
        " . self::divider() . "
        <p style='margin:0;font-size:13px;color:#94A3B8;line-height:1.5;'>
          L'équipe SAT Platform<br>
          <span style='color:#CBD5E1;'>Plateforme de Sensibilisation à la Cybersécurité</span>
        </p>";
    }

    // ─────────────────────────────────────────────────────────────────
    // EMAILS TRANSACTIONNELS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Email envoyé au RSSI après soumission du formulaire d'inscription
     */
    public static function inscriptionRecue(
        string $prenom,
        string $nom,
        string $entrepriseNom,
        string $ifu,
        string $secteur,
        string $nombreEmployes
    ): string {
        $contenu =
            self::badge('Demande reçue', '#DBEAFE', '#1D4ED8') .
            "<div style='margin:20px 0 4px;'>" . self::h1('Votre demande est en cours d\'examen') . "</div>" .
            self::p("Nous avons bien reçu la demande d'inscription de <strong style='color:#1E293B;'>{$entrepriseNom}</strong> sur SAT Platform. Notre équipe va examiner votre dossier.", 'margin-top:12px;') .
            self::infoBox("
              <strong>Prochaines étapes</strong><br><br>
              <span style='opacity:0.85;'>
              ① &nbsp;Vérification de votre dossier sous <strong>48 heures ouvrées</strong><br>
              ② &nbsp;Réception d'un email d'activation de compte<br>
              ③ &nbsp;Définition de votre mot de passe et accès à la plateforme
              </span>
            ") .
            self::dataTable([
                ['Entreprise', $entrepriseNom],
                ['IFU', $ifu],
                ['Secteur', $secteur],
                ['Effectif déclaré', $nombreEmployes . ' employés'],
            ]) .
            self::p("Pour toute question, contactez-nous à <a href='mailto:support@satplatform.bj' style='color:#0EA5E9;text-decoration:none;font-weight:500;'>support@satplatform.bj</a>") .
            self::signature();

        return self::wrap($contenu, 'Inscription reçue');
    }

    /**
     * Email envoyé à l'admin quand une nouvelle inscription arrive
     */
    public static function nouvelleInscriptionAdmin(
        string $entrepriseNom,
        string $ifu,
        string $rccm,
        string $secteur,
        string $nombreEmployes,
        string $emailEntreprise,
        string $telephoneEntreprise,
        string $prenomRssi,
        string $nomRssi,
        string $emailRssi,
        string $telephoneRssi,
        string $urlAdmin
    ): string {
        $contenu =
            self::badge('Action requise', '#FEF9C3', '#92400E') .
            "<div style='margin:20px 0 4px;'>" . self::h1('Nouvelle demande d\'inscription') . "</div>" .
            self::p("Une entreprise souhaite rejoindre SAT Platform et attend votre validation.", 'margin-top:12px;') .
            self::warningBox("<strong>Validation requise</strong> — Consultez le dossier dans l'interface d'administration et acceptez ou refusez la demande.") .
            self::h2('Entreprise') .
            self::dataTable([
                ['Nom', $entrepriseNom],
                ['IFU', $ifu],
                ['RCCM', $rccm ?: '—'],
                ['Secteur', $secteur],
                ['Effectif', $nombreEmployes . ' employés'],
                ['Email', $emailEntreprise],
                ['Téléphone', $telephoneEntreprise ?: '—'],
            ]) .
            self::h2('RSSI désigné') .
            self::dataTable([
                ['Nom complet', "{$prenomRssi} {$nomRssi}"],
                ['Email', $emailRssi],
                ['Téléphone', $telephoneRssi ?: '—'],
            ]) .
            self::btn('Voir la demande dans l\'admin', $urlAdmin, '#0F172A') .
            self::signature();

        return self::wrap($contenu, 'Nouvelle inscription', '#F59E0B');
    }

    /**
     * Email d'activation de compte RSSI (après validation admin)
     */
    public static function activationCompte(
        string $prenom,
        string $nom,
        string $entrepriseNom,
        string $lienActivation
    ): string {
        $contenu =
            self::badge('Compte validé', '#DCFCE7', '#166534') .
            "<div style='margin:20px 0 4px;'>" . self::h1('Votre compte est prêt') . "</div>" .
            self::greeting($prenom, $nom) .
            self::p("Excellente nouvelle — la demande d'inscription de <strong style='color:#1E293B;'>{$entrepriseNom}</strong> a été validée. Votre espace RSSI est maintenant disponible.") .
            self::successBox("<strong>Dernière étape :</strong> Cliquez sur le bouton ci-dessous pour définir votre mot de passe et accéder à SAT Platform.") .
            self::btn('Activer mon compte', $lienActivation, '#0EA5E9') .
            self::p("Ce lien est valide pendant <strong style='color:#1E293B;'>48 heures</strong>. Passé ce délai, contactez le support.", 'font-size:13px;color:#94A3B8;') .
            self::signature();

        return self::wrap($contenu, 'Activation compte', '#0EA5E9');
    }

    /**
     * Email de rejet d'inscription
     */
    public static function inscriptionRejetee(
        string $entrepriseNom,
        string $emailContact
    ): string {
        $contenu =
            self::badge('Demande non retenue', '#FEE2E2', '#B91C1C') .
            "<div style='margin:20px 0 4px;'>" . self::h1('Suite à votre demande d\'inscription') . "</div>" .
            self::p("Bonjour,") .
            self::p("Après examen de votre dossier, nous ne sommes pas en mesure de valider la demande d'inscription de <strong style='color:#1E293B;'>{$entrepriseNom}</strong> à ce stade.") .
            self::infoBox(
                "Pour toute question ou pour soumettre un nouveau dossier, contactez-nous à <a href='mailto:support@satplatform.bj' style='color:#0369A1;font-weight:500;'>support@satplatform.bj</a>",
                '#F0F9FF', '#BAE6FD', '#0369A1'
            ) .
            self::signature();

        return self::wrap($contenu, 'Inscription non retenue', '#64748B');
    }

    /**
     * Email de réinitialisation de mot de passe
     */
    public static function reinitialiserMotDePasse(
        string $prenom,
        string $lienReset
    ): string {
        $contenu =
            self::badge('Sécurité', '#F1F5F9', '#475569') .
            "<div style='margin:20px 0 4px;'>" . self::h1('Réinitialisation du mot de passe') . "</div>" .
            self::greeting($prenom) .
            self::p("Vous avez demandé à réinitialiser le mot de passe de votre compte SAT Platform.") .
            self::btn('Définir un nouveau mot de passe', $lienReset, '#0EA5E9') .
            self::warningBox("<strong>Ce lien expire dans 1 heure.</strong> Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email — votre mot de passe reste inchangé.") .
            self::signature();

        return self::wrap($contenu, 'Réinitialisation mot de passe', '#0EA5E9');
    }

    /**
     * Email de bienvenue employé (création de compte par RSSI)
     */
    public static function bienvenueEmploye(
        string $prenom,
        string $nom,
        string $email,
        string $tempPassword,
        string $entrepriseNom,
        string $departement,
        string $urlLogin
    ): string {
        $contenu =
            self::badge('Compte créé', '#DCFCE7', '#166534') .
            "<div style='margin:20px 0 4px;'>" . self::h1('Bienvenue sur SAT Platform') . "</div>" .
            self::greeting($prenom, $nom) .
            self::p("Le responsable sécurité de <strong style='color:#1E293B;'>{$entrepriseNom}</strong> vous a créé un compte sur SAT Platform, la plateforme de sensibilisation à la cybersécurité de votre organisation.") .
            self::infoBox("
              <strong style='display:block;margin-bottom:12px;'>Vos identifiants de connexion</strong>
              <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                <tr>
                  <td style='font-size:13px;padding:4px 0;color:#0369A1;font-weight:500;width:110px;'>Email</td>
                  <td style='font-size:13px;padding:4px 0;color:#0F172A;font-weight:600;'>{$email}</td>
                </tr>
                <tr>
                  <td style='font-size:13px;padding:4px 0;color:#0369A1;font-weight:500;'>Mot de passe</td>
                  <td style='padding:4px 0;'>
                    <code style='background:#E0F2FE;color:#0369A1;padding:3px 10px;border-radius:5px;font-size:14px;font-weight:700;letter-spacing:0.05em;'>{$tempPassword}</code>
                  </td>
                </tr>" .
                ($departement ? "<tr><td style='font-size:13px;padding:4px 0;color:#0369A1;font-weight:500;'>Département</td><td style='font-size:13px;padding:4px 0;color:#0F172A;font-weight:600;'>{$departement}</td></tr>" : '') .
              "</table>
            ") .
            self::warningBox("Vous serez invité à <strong>changer ce mot de passe</strong> lors de votre première connexion.") .
            self::btn('Accéder à SAT Platform', $urlLogin, '#0EA5E9') .
            self::signature();

        return self::wrap($contenu, 'Bienvenue', '#0EA5E9');
    }
}