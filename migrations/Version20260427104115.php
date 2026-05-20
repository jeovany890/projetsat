<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427104115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression des colonnes inutiles : ordre, temps_limite, melanger_questions, module_id (quiz), ordre_affichage, compteurs campagne_formation';
    }

    public function up(Schema $schema): void
    {
        // Cosmétique score_vigilance détecté par Doctrine
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');

        // ── chapitre ──
    

        // ── quiz ──
        // Supprimer d'abord la FK vers module_formation si elle existe
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY IF EXISTS FK_A412FA92AFC2B591');
        $this->addSql('ALTER TABLE quiz DROP INDEX IF EXISTS UNIQ_A412FA92AFC2B591');
        $this->addSql('ALTER TABLE quiz
            DROP COLUMN temps_limite,
            DROP COLUMN melanger_questions,
            DROP COLUMN module_id
        ');

        // ── module_formation ──
        $this->addSql('ALTER TABLE module_formation DROP COLUMN ordre_affichage');

        // ── campagne_formation — compteurs dénormalisés ──
        $this->addSql('ALTER TABLE campagne_formation
            DROP COLUMN total_participants,
            DROP COLUMN nombre_termines,
            DROP COLUMN nombre_en_cours,
            DROP COLUMN nombre_en_retard
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');

        $this->addSql('ALTER TABLE module_formation ADD ordre_affichage INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE campagne_formation
            ADD total_participants INT NOT NULL DEFAULT 0,
            ADD nombre_termines INT NOT NULL DEFAULT 0,
            ADD nombre_en_cours INT NOT NULL DEFAULT 0,
            ADD nombre_en_retard INT NOT NULL DEFAULT 0
        ');
    }
}
