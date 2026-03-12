<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    private function envoyer(
        string $username,
        string $password,
        string $fromEmail,
        string $fromNom,
        string $destinataire,
        string $sujet,
        string $html
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

        try {
            $mail->send();
        } catch (\Exception $e) {
            throw new \Exception("Erreur d'envoi email : {$mail->ErrorInfo}");
        }
    }

    public function envoyerEmailLeitime(
        string $destinataire,
        string $sujet,
        string $contenuHtml,
        ?string $nomExpediteur = 'SAT Platform',
        ?string $emailExpediteur = null
    ): void {
        $this->envoyer(
            username:     'satplatform.noreply1@gmail.com',
            password:     $_ENV['GMAIL_PASSWORD_LEGITIME'],
            fromEmail:    $emailExpediteur ?? 'satplatform.noreply1@gmail.com',
            fromNom:      $nomExpediteur ?? 'SAT Platform',
            destinataire: $destinataire,
            sujet:        $sujet,
            html:         $contenuHtml
        );
    }

    public function envoyerEmailPhishing(
        string $destinataire,
        string $sujet,
        string $contenuHtml,
        string $nomExpediteur,
        string $emailExpediteur,
        ?string $compteEmailDsn = null
    ): void {
        [$username, $password] = match($compteEmailDsn) {
            'MAILER_PHISHING_BOA'    => ['boa.benini.2026@gmail.com',    $_ENV['GMAIL_PASSWORD_BOA']],
            'MAILER_PHISHING_SBEE'   => ['sbee.benin.2026@gmail.com',    $_ENV['GMAIL_PASSWORD_SBEE']],
            'MAILER_PHISHING_UNICEF' => ['unicef.inter.2026@gmail.com',  $_ENV['GMAIL_PASSWORD_UNICEF']],
            default                  => ['satplatform.noreply1@gmail.com', $_ENV['GMAIL_PASSWORD_LEGITIME']],
        };

        $this->envoyer(
            username:     $username,
            password:     $password,
            fromEmail:    $emailExpediteur,
            fromNom:      $nomExpediteur,
            destinataire: $destinataire,
            sujet:        $sujet,
            html:         $contenuHtml
        );
    }
}