<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311064642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne_formation CHANGE date_debut date_debut DATETIME NOT NULL, CHANGE date_fin date_fin DATETIME NOT NULL, CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE campagne_phishing DROP FOREIGN KEY `FK_2972D5747E839F0C`');
        $this->addSql('DROP INDEX IDX_2972D5747E839F0C ON campagne_phishing');
        $this->addSql('ALTER TABLE campagne_phishing ADD autorisation_confirmee TINYINT DEFAULT 0 NOT NULL, ADD date_autorisation DATETIME DEFAULT NULL, ADD nom_autorisateur VARCHAR(255) DEFAULT NULL, DROP consentement_obtenu, DROP date_consentement, DROP document_consentement, DROP formation_prealable_requise, DROP debriefing_automatique, DROP module_formation_associe_id, CHANGE date_planifiee date_planifiee DATETIME NOT NULL, CHANGE date_debut date_debut DATETIME DEFAULT NULL, CHANGE date_termine date_termine DATETIME DEFAULT NULL, CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE categorie CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE chapitre DROP type_video, CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE departement DROP FOREIGN KEY `FK_C1765B63A4AEAFEA`');
        $this->addSql('ALTER TABLE departement CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE departement ADD CONSTRAINT FK_C1765B63A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE donnees_submises_phishing CHANGE date_submission date_submission DATETIME NOT NULL');
        $this->addSql('ALTER TABLE entreprise ADD charte_acceptee TINYINT DEFAULT 0 NOT NULL, ADD date_acceptation_charte DATETIME DEFAULT NULL, CHANGE date_creation date_creation DATETIME NOT NULL, CHANGE date_validation date_validation DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE envoi_phishing CHANGE date_envoi date_envoi DATETIME DEFAULT NULL, CHANGE date_planifiee date_planifiee DATETIME NOT NULL, CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE gabarit_phishing CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE module_formation CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE notification CHANGE date_creation date_creation DATETIME NOT NULL, CHANGE date_lecture date_lecture DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX unique_progression ON progression_module');
        $this->addSql('ALTER TABLE progression_module CHANGE echeance echeance DATETIME DEFAULT NULL, CHANGE date_debut date_debut DATETIME DEFAULT NULL, CHANGE date_termine date_termine DATETIME DEFAULT NULL, CHANGE date_dernier_acces date_dernier_acces DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE resultat_phishing CHANGE date_envoi date_envoi DATETIME DEFAULT NULL, CHANGE date_ouverture date_ouverture DATETIME DEFAULT NULL, CHANGE date_clic date_clic DATETIME DEFAULT NULL, CHANGE date_submission date_submission DATETIME DEFAULT NULL, CHANGE date_signalement date_signalement DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE resultat_simulation CHANGE date_debut date_debut DATETIME NOT NULL, CHANGE date_termine date_termine DATETIME NOT NULL');
        $this->addSql('ALTER TABLE simulation_interactive CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE tentative_quiz CHANGE date_debut date_debut DATETIME NOT NULL, CHANGE date_termine date_termine DATETIME NOT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE mot_de_passe mot_de_passe VARCHAR(255) NOT NULL, CHANGE reset_password_expiration reset_password_expiration DATETIME DEFAULT NULL, CHANGE date_creation date_creation DATETIME NOT NULL, CHANGE date_derniere_connexion date_derniere_connexion DATETIME DEFAULT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE jeton_expiration jeton_expiration DATETIME DEFAULT NULL, CHANGE total_points total_points INT DEFAULT 0, CHANGE total_etoiles total_etoiles INT DEFAULT 0, CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne_formation CHANGE date_debut date_debut VARCHAR(255) NOT NULL, CHANGE date_fin date_fin VARCHAR(255) NOT NULL, CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE campagne_phishing ADD consentement_obtenu TINYINT DEFAULT NULL, ADD document_consentement VARCHAR(255) DEFAULT NULL, ADD formation_prealable_requise TINYINT DEFAULT NULL, ADD debriefing_automatique TINYINT DEFAULT NULL, ADD module_formation_associe_id INT DEFAULT NULL, DROP autorisation_confirmee, DROP date_autorisation, CHANGE date_planifiee date_planifiee VARCHAR(255) NOT NULL, CHANGE date_debut date_debut VARCHAR(255) DEFAULT NULL, CHANGE date_termine date_termine VARCHAR(255) DEFAULT NULL, CHANGE date_creation date_creation VARCHAR(255) NOT NULL, CHANGE nom_autorisateur date_consentement VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE campagne_phishing ADD CONSTRAINT `FK_2972D5747E839F0C` FOREIGN KEY (module_formation_associe_id) REFERENCES module_formation (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2972D5747E839F0C ON campagne_phishing (module_formation_associe_id)');
        $this->addSql('ALTER TABLE categorie CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE chapitre ADD type_video VARCHAR(20) DEFAULT NULL, CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE departement DROP FOREIGN KEY FK_C1765B63A4AEAFEA');
        $this->addSql('ALTER TABLE departement CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE departement ADD CONSTRAINT `FK_C1765B63A4AEAFEA` FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE donnees_submises_phishing CHANGE date_submission date_submission VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE entreprise DROP charte_acceptee, DROP date_acceptation_charte, CHANGE date_creation date_creation VARCHAR(255) NOT NULL, CHANGE date_validation date_validation VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE envoi_phishing CHANGE date_planifiee date_planifiee VARCHAR(255) NOT NULL, CHANGE date_envoi date_envoi VARCHAR(255) DEFAULT NULL, CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE gabarit_phishing CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE module_formation CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE notification CHANGE date_creation date_creation VARCHAR(255) NOT NULL, CHANGE date_lecture date_lecture VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE progression_module CHANGE echeance echeance VARCHAR(255) DEFAULT NULL, CHANGE date_debut date_debut VARCHAR(255) DEFAULT NULL, CHANGE date_termine date_termine VARCHAR(255) DEFAULT NULL, CHANGE date_dernier_acces date_dernier_acces VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_progression ON progression_module (employe_id, module_id, campagne_id)');
        $this->addSql('ALTER TABLE quiz CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE resultat_phishing CHANGE date_envoi date_envoi VARCHAR(255) DEFAULT NULL, CHANGE date_ouverture date_ouverture VARCHAR(255) DEFAULT NULL, CHANGE date_clic date_clic VARCHAR(255) DEFAULT NULL, CHANGE date_submission date_submission VARCHAR(255) DEFAULT NULL, CHANGE date_signalement date_signalement VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE resultat_simulation CHANGE date_debut date_debut VARCHAR(255) NOT NULL, CHANGE date_termine date_termine VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE simulation_interactive CHANGE date_creation date_creation VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE tentative_quiz CHANGE date_debut date_debut VARCHAR(255) NOT NULL, CHANGE date_termine date_termine VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE mot_de_passe mot_de_passe VARCHAR(255) DEFAULT NULL, CHANGE reset_password_expiration reset_password_expiration VARCHAR(255) DEFAULT NULL, CHANGE date_creation date_creation VARCHAR(255) NOT NULL, CHANGE date_derniere_connexion date_derniere_connexion VARCHAR(255) DEFAULT NULL, CHANGE type type VARCHAR(20) NOT NULL, CHANGE jeton_expiration jeton_expiration VARCHAR(255) DEFAULT NULL, CHANGE total_points total_points INT DEFAULT 0 NOT NULL, CHANGE total_etoiles total_etoiles INT DEFAULT 0 NOT NULL, CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\' NOT NULL');
    }
}
