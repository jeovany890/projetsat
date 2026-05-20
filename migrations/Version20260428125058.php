<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428125058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1 — Suppression des champs inutiles et dénormalisés';
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :table
               AND COLUMN_NAME  = :column",
            ['table' => $table, 'column' => $column]
        );
        return (int) $result > 0;
    }

    public function up(Schema $schema): void
    {
        // 1. CHAPITRE — supprimer ordre
        if ($this->columnExists('chapitre', 'ordre')) {
            $this->addSql('ALTER TABLE chapitre DROP COLUMN ordre');
        }

        // 2. QUESTION_QUIZ — supprimer ordre
        if ($this->columnExists('question_quiz', 'ordre')) {
            $this->addSql('ALTER TABLE question_quiz DROP COLUMN ordre');
        }

        // 3. QUIZ — supprimer temps_limite, melanger_questions, module_id
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY IF EXISTS FK_A412FA92AFC2B591');
        $this->addSql('ALTER TABLE quiz DROP INDEX IF EXISTS UNIQ_A412FA92AFC2B591');
        $quizDrops = [];
        foreach (['temps_limite', 'melanger_questions', 'module_id'] as $col) {
            if ($this->columnExists('quiz', $col)) {
                $quizDrops[] = "DROP COLUMN $col";
            }
        }
        if (!empty($quizDrops)) {
            $this->addSql('ALTER TABLE quiz ' . implode(', ', $quizDrops));
        }

        // 4. MODULE_FORMATION — supprimer ordre_affichage
        if ($this->columnExists('module_formation', 'ordre_affichage')) {
            $this->addSql('ALTER TABLE module_formation DROP COLUMN ordre_affichage');
        }

        // 5. CAMPAGNE_PHISHING — supprimer compteurs dénormalisés
        $campagneDrops = [];
        foreach (['canal', 'sms_envoyes', 'total_cibles', 'emails_envoyes', 'liens_cliques', 'emails_signales'] as $col) {
            if ($this->columnExists('campagne_phishing', $col)) {
                $campagneDrops[] = "DROP COLUMN $col";
            }
        }
        if (!empty($campagneDrops)) {
            $this->addSql('ALTER TABLE campagne_phishing ' . implode(', ', $campagneDrops));
        }

        // 6. RESULTAT_PHISHING — supprimer champs de tracking non utilisés
        $resultatDrops = [];
        foreach (['email_signale', 'date_signalement', 'adresse_ip', 'agent_utilisateur'] as $col) {
            if ($this->columnExists('resultat_phishing', $col)) {
                $resultatDrops[] = "DROP COLUMN $col";
            }
        }
        if (!empty($resultatDrops)) {
            $this->addSql('ALTER TABLE resultat_phishing ' . implode(', ', $resultatDrops));
        }

        // 7. GABARIT_PHISHING — supprimer canal et champs SMS
        $gabaritDrops = [];
        foreach (['canal', 'contenu_sms', 'expediteur_sms'] as $col) {
            if ($this->columnExists('gabarit_phishing', $col)) {
                $gabaritDrops[] = "DROP COLUMN $col";
            }
        }
        if (!empty($gabaritDrops)) {
            $this->addSql('ALTER TABLE gabarit_phishing ' . implode(', ', $gabaritDrops));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chapitre ADD ordre INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE question_quiz ADD ordre INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE quiz
            ADD temps_limite INT DEFAULT NULL,
            ADD melanger_questions TINYINT(4) NOT NULL DEFAULT 1,
            ADD module_id INT DEFAULT NULL
        ');
        $this->addSql('ALTER TABLE module_formation ADD ordre_affichage INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE campagne_phishing
            ADD canal VARCHAR(10) NOT NULL DEFAULT \'EMAIL\',
            ADD sms_envoyes INT NOT NULL DEFAULT 0,
            ADD total_cibles INT NOT NULL DEFAULT 0,
            ADD emails_envoyes INT NOT NULL DEFAULT 0,
            ADD liens_cliques INT NOT NULL DEFAULT 0,
            ADD emails_signales INT NOT NULL DEFAULT 0
        ');
        $this->addSql('ALTER TABLE resultat_phishing
            ADD email_signale TINYINT(4) NOT NULL DEFAULT 0,
            ADD date_signalement DATETIME DEFAULT NULL,
            ADD adresse_ip VARCHAR(45) DEFAULT NULL,
            ADD agent_utilisateur LONGTEXT DEFAULT NULL
        ');
        $this->addSql('ALTER TABLE gabarit_phishing
            ADD canal VARCHAR(10) NOT NULL DEFAULT \'EMAIL\',
            ADD contenu_sms VARCHAR(320) DEFAULT NULL,
            ADD expediteur_sms VARCHAR(100) DEFAULT NULL
        ');
    }
}