<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319111919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne_phishing ADD canal VARCHAR(10) DEFAULT \'EMAIL\' NOT NULL, ADD sms_envoyes INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE gabarit_phishing ADD canal VARCHAR(10) DEFAULT \'EMAIL\' NOT NULL, ADD contenu_sms VARCHAR(320) DEFAULT NULL, ADD expediteur_sms VARCHAR(20) DEFAULT NULL, CHANGE compte_email_dsn compte_email_dsn VARCHAR(100) DEFAULT NULL, CHANGE nom_expediteur nom_expediteur VARCHAR(255) DEFAULT NULL, CHANGE email_expediteur email_expediteur VARCHAR(255) DEFAULT NULL, CHANGE sujet_email sujet_email VARCHAR(255) DEFAULT NULL, CHANGE contenu_html contenu_html LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne_phishing DROP canal, DROP sms_envoyes');
        $this->addSql('ALTER TABLE gabarit_phishing DROP canal, DROP contenu_sms, DROP expediteur_sms, CHANGE compte_email_dsn compte_email_dsn VARCHAR(100) NOT NULL, CHANGE nom_expediteur nom_expediteur VARCHAR(255) NOT NULL, CHANGE email_expediteur email_expediteur VARCHAR(255) NOT NULL, CHANGE sujet_email sujet_email VARCHAR(255) NOT NULL, CHANGE contenu_html contenu_html LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
