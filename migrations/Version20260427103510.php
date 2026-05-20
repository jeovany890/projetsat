<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427103510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE signalement_phishing (id INT AUTO_INCREMENT NOT NULL, domaine_signale VARCHAR(255) NOT NULL, contenu_email LONGTEXT NOT NULL, date_signalement DATETIME NOT NULL, est_valide TINYINT DEFAULT 0 NOT NULL, points_attribues INT DEFAULT 0 NOT NULL, employe_id INT NOT NULL, campagne_id INT DEFAULT NULL, resultat_id INT DEFAULT NULL, INDEX IDX_CBB08B2A1B65292 (employe_id), INDEX IDX_CBB08B2A16227374 (campagne_id), INDEX IDX_CBB08B2AD233E95C (resultat_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE signalement_phishing ADD CONSTRAINT FK_CBB08B2A1B65292 FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE signalement_phishing ADD CONSTRAINT FK_CBB08B2A16227374 FOREIGN KEY (campagne_id) REFERENCES campagne_phishing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE signalement_phishing ADD CONSTRAINT FK_CBB08B2AD233E95C FOREIGN KEY (resultat_id) REFERENCES resultat_phishing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE campagne_phishing DROP total_cibles, DROP emails_envoyes, DROP liens_cliques, DROP emails_signales, DROP canal, DROP sms_envoyes, DROP emails_ouverts, DROP donnees_submises');
        $this->addSql('ALTER TABLE envoi_phishing DROP FOREIGN KEY `FK_B12E6394D233E95C`');
        $this->addSql('DROP INDEX UNIQ_B12E6394D233E95C ON envoi_phishing');
        $this->addSql('ALTER TABLE envoi_phishing DROP resultat_id');
        $this->addSql('ALTER TABLE gabarit_phishing DROP canal, DROP contenu_sms, DROP expediteur_sms');
        $this->addSql('ALTER TABLE module_formation DROP INDEX FK_module_simulation, ADD UNIQUE INDEX UNIQ_1A213E77FEC09103 (simulation_id)');
        $this->addSql('ALTER TABLE resultat_phishing ADD comportement VARCHAR(20) DEFAULT \'PASSIF\' NOT NULL, ADD envoi_id INT DEFAULT NULL, DROP email_signale, DROP date_signalement, DROP adresse_ip, DROP agent_utilisateur');
        $this->addSql('ALTER TABLE resultat_phishing ADD CONSTRAINT FK_273863F83F97ECE5 FOREIGN KEY (envoi_id) REFERENCES envoi_phishing (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_273863F83F97ECE5 ON resultat_phishing (envoi_id)');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE signalement_phishing DROP FOREIGN KEY FK_CBB08B2A1B65292');
        $this->addSql('ALTER TABLE signalement_phishing DROP FOREIGN KEY FK_CBB08B2A16227374');
        $this->addSql('ALTER TABLE signalement_phishing DROP FOREIGN KEY FK_CBB08B2AD233E95C');
        $this->addSql('DROP TABLE signalement_phishing');
        $this->addSql('ALTER TABLE campagne_phishing ADD total_cibles INT DEFAULT 0 NOT NULL, ADD emails_envoyes INT DEFAULT 0 NOT NULL, ADD liens_cliques INT DEFAULT 0 NOT NULL, ADD emails_signales INT DEFAULT 0 NOT NULL, ADD canal VARCHAR(10) DEFAULT \'EMAIL\' NOT NULL, ADD sms_envoyes INT DEFAULT 0 NOT NULL, ADD emails_ouverts INT DEFAULT 0 NOT NULL, ADD donnees_submises INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE envoi_phishing ADD resultat_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE envoi_phishing ADD CONSTRAINT `FK_B12E6394D233E95C` FOREIGN KEY (resultat_id) REFERENCES resultat_phishing (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B12E6394D233E95C ON envoi_phishing (resultat_id)');
        $this->addSql('ALTER TABLE gabarit_phishing ADD canal VARCHAR(10) DEFAULT \'EMAIL\' NOT NULL, ADD contenu_sms VARCHAR(320) DEFAULT NULL, ADD expediteur_sms VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE module_formation DROP INDEX UNIQ_1A213E77FEC09103, ADD INDEX FK_module_simulation (simulation_id)');
        $this->addSql('ALTER TABLE resultat_phishing DROP FOREIGN KEY FK_273863F83F97ECE5');
        $this->addSql('DROP INDEX UNIQ_273863F83F97ECE5 ON resultat_phishing');
        $this->addSql('ALTER TABLE resultat_phishing ADD email_signale TINYINT DEFAULT 0 NOT NULL, ADD date_signalement DATETIME DEFAULT NULL, ADD adresse_ip VARCHAR(45) DEFAULT NULL, ADD agent_utilisateur LONGTEXT DEFAULT NULL, DROP comportement, DROP envoi_id');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
