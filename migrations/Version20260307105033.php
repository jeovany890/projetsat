<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260307105033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE envoi_phishing (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, date_envoi DATETIME NOT NULL, est_envoye TINYINT DEFAULT 0 NOT NULL, message_erreur LONGTEXT DEFAULT NULL, date_ouverture DATETIME DEFAULT NULL, adresse_ip_ouverture VARCHAR(45) DEFAULT NULL, user_agent_ouverture LONGTEXT DEFAULT NULL, ville_ouverture VARCHAR(100) DEFAULT NULL, pays_ouverture VARCHAR(100) DEFAULT NULL, date_clic DATETIME DEFAULT NULL, a_clique TINYINT DEFAULT 0 NOT NULL, adresse_ip_clic VARCHAR(45) DEFAULT NULL, user_agent_clic LONGTEXT DEFAULT NULL, date_signalement DATETIME DEFAULT NULL, a_signale TINYINT DEFAULT 0 NOT NULL, campagne_id INT NOT NULL, employe_id INT NOT NULL, UNIQUE INDEX UNIQ_B12E63945F37A13B (token), INDEX IDX_B12E639416227374 (campagne_id), INDEX IDX_B12E63941B65292 (employe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE envoi_phishing ADD CONSTRAINT FK_B12E639416227374 FOREIGN KEY (campagne_id) REFERENCES campagne_phishing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE envoi_phishing ADD CONSTRAINT FK_B12E63941B65292 FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE envoi_phishing DROP FOREIGN KEY FK_B12E639416227374');
        $this->addSql('ALTER TABLE envoi_phishing DROP FOREIGN KEY FK_B12E63941B65292');
        $this->addSql('DROP TABLE envoi_phishing');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
