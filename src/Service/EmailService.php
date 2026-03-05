<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    // Les 4 comptes Gmail
    private array $comptes = [
        'legitime' => [
            'email' => 'satplatform.noreply1@gmail.com',
            'password' => '', // Tu vas remplir après
        ],
        'boa' => [
            'email' => 'boa.benini.2026@gmail.com',
            'password' => '',
        ],
        'sbee' => [
            'email' => 'sbee.benin.2026@gmail.com',
            'password' => '',
        ],
        'unicef' => [
            'email' => 'unicef.inter.2026@gmail.com',
            'password' => '',
        ],
    ];

    public function __construct()
    {
        // Charger les mots de passe depuis variables d'environnement
        $this->comptes['legitime']['password'] = $_ENV['GMAIL_PASSWORD_LEGITIME'] ?? '';
        $this->comptes['boa']['password'] = $_ENV['GMAIL_PASSWORD_BOA'] ?? '';
        $this->comptes['sbee']['password'] = $_ENV['GMAIL_PASSWORD_SBEE'] ?? '';
        $this->comptes['unicef']['password'] = $_ENV['GMAIL_PASSWORD_UNICEF'] ?? '';
    }

    /**
     * Envoyer un email légitime (notifications SAT Platform)
     */
    public function envoyerEmailLeitime(
        string $destinataire, 
        string $sujet, 
        string $contenuHtml
    ): bool {
        return $this->envoyerEmail(
            $this->comptes['legitime']['email'],
            $this->comptes['legitime']['password'],
            'SAT Platform',
            $destinataire,
            $sujet,
            $contenuHtml
        );
    }

    /**
     * Envoyer un email phishing BOA
     */
    public function envoyerPhishingBOA(
        string $destinataire,
        string $nomExpediteur,
        string $sujet,
        string $contenuHtml
    ): bool {
        return $this->envoyerEmail(
            $this->comptes['boa']['email'],
            $this->comptes['boa']['password'],
            $nomExpediteur,
            $destinataire,
            $sujet,
            $contenuHtml
        );
    }

    /**
     * Envoyer un email phishing SBEE
     */
    public function envoyerPhishingSBEE(
        string $destinataire,
        string $nomExpediteur,
        string $sujet,
        string $contenuHtml
    ): bool {
        return $this->envoyerEmail(
            $this->comptes['sbee']['email'],
            $this->comptes['sbee']['password'],
            $nomExpediteur,
            $destinataire,
            $sujet,
            $contenuHtml
        );
    }

    /**
     * Envoyer un email phishing UNICEF
     */
    public function envoyerPhishingUNICEF(
        string $destinataire,
        string $nomExpediteur,
        string $sujet,
        string $contenuHtml
    ): bool {
        return $this->envoyerEmail(
            $this->comptes['unicef']['email'],
            $this->comptes['unicef']['password'],
            $nomExpediteur,
            $destinataire,
            $sujet,
            $contenuHtml
        );
    }

    /**
     * Méthode générique pour envoyer un email
     */
    private function envoyerEmail(
        string $emailExpediteur,
        string $motDePasse,
        string $nomExpediteur,
        string $destinataire,
        string $sujet,
        string $contenuHtml
    ): bool {
        $mail = new PHPMailer(true);

        try {
            // Configuration serveur SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $emailExpediteur;
            $mail->Password = $motDePasse;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            // Expéditeur
            $mail->setFrom($emailExpediteur, $nomExpediteur);

            // Destinataire
            $mail->addAddress($destinataire);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $sujet;
            $mail->Body = $contenuHtml;

            // Envoyer
            $mail->send();
            return true;

        } catch (Exception $e) {
            // Log l'erreur
            error_log("Erreur envoi email : " . $mail->ErrorInfo);
            return false;
        }
    }
}