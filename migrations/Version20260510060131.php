<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260510060131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE signalement_phishing DROP FOREIGN KEY `FK_CBB08B2A16227374`');
        $this->addSql('ALTER TABLE signalement_phishing DROP FOREIGN KEY `FK_CBB08B2A1B65292`');
        $this->addSql('ALTER TABLE signalement_phishing DROP FOREIGN KEY `FK_CBB08B2AD233E95C`');
        $this->addSql('DROP TABLE signalement_phishing');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE signalement_phishing (id INT AUTO_INCREMENT NOT NULL, domaine_signale VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, contenu_email LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_signalement DATETIME NOT NULL, est_valide TINYINT DEFAULT 0 NOT NULL, points_attribues INT DEFAULT 0 NOT NULL, employe_id INT NOT NULL, campagne_id INT DEFAULT NULL, resultat_id INT DEFAULT NULL, INDEX IDX_CBB08B2A1B65292 (employe_id), INDEX IDX_CBB08B2A16227374 (campagne_id), INDEX IDX_CBB08B2AD233E95C (resultat_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE signalement_phishing ADD CONSTRAINT `FK_CBB08B2A16227374` FOREIGN KEY (campagne_id) REFERENCES campagne_phishing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE signalement_phishing ADD CONSTRAINT `FK_CBB08B2A1B65292` FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE signalement_phishing ADD CONSTRAINT `FK_CBB08B2AD233E95C` FOREIGN KEY (resultat_id) REFERENCES resultat_phishing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
