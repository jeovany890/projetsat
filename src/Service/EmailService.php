<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    /**
     * Envoyer un email standard (notifications, alertes, etc.)
     */
    public function envoyerEmailLeitime(
        string $destinataire,
        string $sujet,
        string $contenuHtml,
        ?string $nomExpediteur = 'SAT Platform',
        ?string $emailExpediteur = null
    ): void {
        $mail = new PHPMailer(true);

        try {
            // Récupérer le DSN depuis les variables d'environnement
            $dsn = $_ENV['MAILER_DSN'];
            $dsnParts = parse_url($dsn);
            parse_str($dsnParts['query'] ?? '', $params);

            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = $dsnParts['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $dsnParts['user'];
            $mail->Password = $dsnParts['pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $dsnParts['port'] ?? 587;
            $mail->CharSet = 'UTF-8';

            // Expéditeur
            $fromEmail = $emailExpediteur ?? $dsnParts['user'];
            $mail->setFrom($fromEmail, $nomExpediteur);
            $mail->addAddress($destinataire);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $sujet;
            $mail->Body = $contenuHtml;

            $mail->send();
        } catch (\Exception $e) {
            throw new \Exception("Erreur d'envoi email : {$mail->ErrorInfo}");
        }
    }

    /**
     * Envoyer un email de phishing (avec compte spécifique BOA/SBEE/UNICEF)
     */
    public function envoyerEmailPhishing(
        string $destinataire,
        string $sujet,
        string $contenuHtml,
        string $nomExpediteur,
        string $emailExpediteur,
        ?string $compteEmailDsn = null
    ): void {
        // Sélectionner le bon compte email selon le DSN
        $dsn = match($compteEmailDsn) {
            'MAILER_PHISHING_BOA' => $_ENV['MAILER_PHISHING_BOA'] ?? $_ENV['MAILER_DSN'],
            'MAILER_PHISHING_SBEE' => $_ENV['MAILER_PHISHING_SBEE'] ?? $_ENV['MAILER_DSN'],
            'MAILER_PHISHING_UNICEF' => $_ENV['MAILER_PHISHING_UNICEF'] ?? $_ENV['MAILER_DSN'],
            default => $_ENV['MAILER_DSN']
        };

        $mail = new PHPMailer(true);

        try {
            // Parser le DSN
            $dsnParts = parse_url($dsn);
            parse_str($dsnParts['query'] ?? '', $params);

            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = $dsnParts['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $dsnParts['user'];
            $mail->Password = $dsnParts['pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $dsnParts['port'] ?? 587;
            $mail->CharSet = 'UTF-8';

            // Expéditeur personnalisé (celui du gabarit de phishing)
            $mail->setFrom($emailExpediteur, $nomExpediteur);
            $mail->addAddress($destinataire);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $sujet;
            $mail->Body = $contenuHtml;

            $mail->send();
        } catch (\Exception $e) {
            throw new \Exception("Erreur d'envoi email phishing : {$mail->ErrorInfo}");
        }
    }
}