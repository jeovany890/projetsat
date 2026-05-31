<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    /**
     * Envoi SMTP de base.
     *
     * @param array $embeds  [ ['path' => '/...', 'cid' => 'logo@sat', 'name' => 'logo.jpg'] ]
     */
    private function envoyer(
        string $username,
        string $password,
        string $fromEmail,
        string $fromNom,
        string $destinataire,
        string $sujet,
        string $html,
        array  $embeds = []
    ): void {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($fromEmail, $fromNom);
        $mail->addAddress($destinataire);
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        // ── Images embarquées (CID) ────────────────────────────────
        // Affichées par Gmail/Outlook SANS demande d'autorisation
        // car elles sont dans le corps MIME du message lui-même.
        foreach ($embeds as $embed) {
            if (!empty($embed['path']) && file_exists($embed['path'])) {
                $mail->addEmbeddedImage(
                    $embed['path'],          // chemin absolu du fichier
                    $embed['cid'],           // Content-ID → utilisé comme src="cid:xxx"
                    $embed['name'] ?? 'image.jpg',
                    PHPMailer::ENCODING_BASE64,
                    $embed['mime'] ?? 'image/jpeg'
                );
            }
        }

        try {
            $mail->send();
        } catch (\Exception $e) {
            throw new \Exception("Erreur d'envoi email : {$mail->ErrorInfo}");
        }
    }

    // ── Email légitime (notifications, formations, etc.) ──────────
    public function envoyerEmailLeitime(
        string  $destinataire,
        string  $sujet,
        string  $contenuHtml,
        ?string $nomExpediteur  = 'SAT Platform',
        ?string $emailExpediteur = null,
        array   $embeds = []
    ): void {
        $this->envoyer(
            username:     'satplatform.noreply1@gmail.com',
            password:     $_ENV['GMAIL_PASSWORD_LEGITIME'],
            fromEmail:    $emailExpediteur ?? 'satplatform.noreply1@gmail.com',
            fromNom:      $nomExpediteur ?? 'SAT Platform',
            destinataire: $destinataire,
            sujet:        $sujet,
            html:         $contenuHtml,
            embeds:       $embeds
        );
    }

    // ── Email phishing simulé ─────────────────────────────────────
    public function envoyerEmailPhishing(
        string  $destinataire,
        string  $sujet,
        string  $contenuHtml,
        string  $nomExpediteur,
        string  $emailExpediteur,
        ?string $compteEmailDsn = null,
        array   $embeds = []
    ): void {
        [$username, $password] = match($compteEmailDsn) {
            default => ['satplatform.noreply1@gmail.com', $_ENV['GMAIL_PASSWORD_PHISHING']],
        };

        $this->envoyer(
            username:     $username,
            password:     $password,
            fromEmail:    $emailExpediteur,
            fromNom:      $nomExpediteur,
            destinataire: $destinataire,
            sujet:        $sujet,
            html:         $contenuHtml,
            embeds:       $embeds
        );
    }
}