<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260308171540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_B12E63945F37A13B ON envoi_phishing');
        $this->addSql('ALTER TABLE envoi_phishing ADD statut VARCHAR(20) DEFAULT \'PLANIFIE\' NOT NULL, ADD email_destinataire VARCHAR(255) NOT NULL, ADD sujet_utilise VARCHAR(255) NOT NULL, ADD date_planifiee DATETIME NOT NULL, ADD nombre_tentatives INT DEFAULT 0 NOT NULL, ADD date_creation DATETIME NOT NULL, ADD resultat_id INT DEFAULT NULL, DROP token, DROP est_envoye, DROP date_ouverture, DROP adresse_ip_ouverture, DROP user_agent_ouverture, DROP ville_ouverture, DROP pays_ouverture, DROP date_clic, DROP a_clique, DROP adresse_ip_clic, DROP user_agent_clic, DROP date_signalement, DROP a_signale, CHANGE date_envoi date_envoi DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE envoi_phishing ADD CONSTRAINT FK_B12E6394D233E95C FOREIGN KEY (resultat_id) REFERENCES resultat_phishing (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B12E6394D233E95C ON envoi_phishing (resultat_id)');
        $this->addSql('ALTER TABLE utilisateur ADD entreprise_id INT DEFAULT NULL, CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B3A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1D1C63B3A4AEAFEA ON utilisateur (entreprise_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE envoi_phishing DROP FOREIGN KEY FK_B12E6394D233E95C');
        $this->addSql('DROP INDEX UNIQ_B12E6394D233E95C ON envoi_phishing');
        $this->addSql('ALTER TABLE envoi_phishing ADD token VARCHAR(64) NOT NULL, ADD est_envoye TINYINT DEFAULT 0 NOT NULL, ADD date_ouverture DATETIME DEFAULT NULL, ADD adresse_ip_ouverture VARCHAR(45) DEFAULT NULL, ADD user_agent_ouverture LONGTEXT DEFAULT NULL, ADD ville_ouverture VARCHAR(100) DEFAULT NULL, ADD pays_ouverture VARCHAR(100) DEFAULT NULL, ADD date_clic DATETIME DEFAULT NULL, ADD a_clique TINYINT DEFAULT 0 NOT NULL, ADD adresse_ip_clic VARCHAR(45) DEFAULT NULL, ADD user_agent_clic LONGTEXT DEFAULT NULL, ADD date_signalement DATETIME DEFAULT NULL, ADD a_signale TINYINT DEFAULT 0 NOT NULL, DROP statut, DROP email_destinataire, DROP sujet_utilise, DROP date_planifiee, DROP nombre_tentatives, DROP date_creation, DROP resultat_id, CHANGE date_envoi date_envoi DATETIME NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B12E63945F37A13B ON envoi_phishing (token)');
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B3A4AEAFEA');
        $this->addSql('DROP INDEX IDX_1D1C63B3A4AEAFEA ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur DROP entreprise_id, CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
