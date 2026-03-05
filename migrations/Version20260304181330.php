<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304181330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE campagne_formation (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, trimestre VARCHAR(10) NOT NULL, annee INT NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, statut VARCHAR(20) DEFAULT \'PLANIFIEE\' NOT NULL, points_penalite INT DEFAULT 50 NOT NULL, total_participants INT DEFAULT 0 NOT NULL, nombre_termines INT DEFAULT 0 NOT NULL, nombre_en_cours INT DEFAULT 0 NOT NULL, nombre_en_retard INT DEFAULT 0 NOT NULL, date_creation DATETIME NOT NULL, rssi_id INT NOT NULL, INDEX IDX_C4A68CA1A715FA7 (rssi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE campagne_formation_module (campagne_formation_id INT NOT NULL, module_formation_id INT NOT NULL, INDEX IDX_7BF1DEB516782339 (campagne_formation_id), INDEX IDX_7BF1DEB53A53B0DC (module_formation_id), PRIMARY KEY (campagne_formation_id, module_formation_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE campagne_phishing (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, statut VARCHAR(20) DEFAULT \'PLANIFIEE\' NOT NULL, date_planifiee DATETIME NOT NULL, date_debut DATETIME DEFAULT NULL, date_termine DATETIME DEFAULT NULL, total_cibles INT DEFAULT 0 NOT NULL, emails_envoyes INT DEFAULT 0 NOT NULL, emails_ouverts INT DEFAULT 0 NOT NULL, liens_cliques INT DEFAULT 0 NOT NULL, donnees_submises INT DEFAULT 0 NOT NULL, emails_signales INT DEFAULT 0 NOT NULL, date_creation DATETIME NOT NULL, rssi_id INT NOT NULL, gabarit_id INT NOT NULL, INDEX IDX_2972D574A715FA7 (rssi_id), INDEX IDX_2972D5748B339010 (gabarit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, statut VARCHAR(20) DEFAULT \'ACTIF\' NOT NULL, date_creation DATETIME NOT NULL, UNIQUE INDEX UNIQ_497DD634FF7747B4 (titre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chapitre (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, url_video VARCHAR(255) DEFAULT NULL, duree_video INT DEFAULT NULL, ordre INT NOT NULL, date_creation DATETIME NOT NULL, module_id INT NOT NULL, INDEX IDX_8C62B025AFC2B591 (module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE departement (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, entreprise_id INT NOT NULL, INDEX IDX_C1765B63A4AEAFEA (entreprise_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE donnees_submises_phishing (id INT AUTO_INCREMENT NOT NULL, champs_submis JSON NOT NULL, nombre_champs INT NOT NULL, gravite VARCHAR(20) NOT NULL, adresse_ip VARCHAR(45) NOT NULL, agent_utilisateur LONGTEXT NOT NULL, date_submission DATETIME NOT NULL, resultat_phishing_id INT NOT NULL, UNIQUE INDEX UNIQ_E8A24A4BB446785 (resultat_phishing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entreprise (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, ifu VARCHAR(13) NOT NULL, rccm VARCHAR(50) NOT NULL, secteur VARCHAR(100) NOT NULL, nombre_employes INT DEFAULT 0 NOT NULL, telephone VARCHAR(20) NOT NULL, email VARCHAR(180) NOT NULL, adresse LONGTEXT DEFAULT NULL, statut VARCHAR(20) DEFAULT \'EN_ATTENTE\' NOT NULL, date_creation DATETIME NOT NULL, date_validation DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_D19FA60AB8F602F (ifu), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE gabarit_phishing (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, categorie VARCHAR(100) NOT NULL, difficulte VARCHAR(20) NOT NULL, compte_email_dsn VARCHAR(100) NOT NULL, nom_expediteur VARCHAR(255) NOT NULL, email_expediteur VARCHAR(255) NOT NULL, sujet_email VARCHAR(255) NOT NULL, contenu_html LONGTEXT NOT NULL, contenu_texte LONGTEXT DEFAULT NULL, indices_pieges JSON DEFAULT NULL, est_actif TINYINT DEFAULT 1 NOT NULL, nombre_utilisations INT DEFAULT 0 NOT NULL, date_creation DATETIME NOT NULL, administrateur_id INT NOT NULL, UNIQUE INDEX UNIQ_C80D3E6E989D9B62 (slug), INDEX IDX_C80D3E6E7EE5403C (administrateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE module_formation (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, type_module VARCHAR(50) NOT NULL, difficulte VARCHAR(20) NOT NULL, duree_estimee INT NOT NULL, points_reussite INT DEFAULT 100 NOT NULL, etoiles_reussite INT DEFAULT 2 NOT NULL, ordre_affichage INT DEFAULT 0 NOT NULL, est_publie TINYINT DEFAULT 0 NOT NULL, date_creation DATETIME NOT NULL, categorie_id INT NOT NULL, UNIQUE INDEX UNIQ_1A213E77989D9B62 (slug), INDEX IDX_1A213E77BCF5E72D (categorie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, titre VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, url_action VARCHAR(255) DEFAULT NULL, est_lu TINYINT DEFAULT 0 NOT NULL, date_creation DATETIME NOT NULL, date_lecture DATETIME DEFAULT NULL, utilisateur_id INT NOT NULL, INDEX IDX_BF5476CAFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE progression_module (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(20) DEFAULT \'NON_COMMENCE\' NOT NULL, pourcentage_progression INT DEFAULT 0 NOT NULL, temps_passe_minutes INT DEFAULT 0 NOT NULL, score_quiz INT DEFAULT NULL, points_gagnes INT DEFAULT 0 NOT NULL, etoiles_gagnees INT DEFAULT 0 NOT NULL, echeance DATETIME DEFAULT NULL, est_en_retard TINYINT DEFAULT 0 NOT NULL, date_debut DATETIME DEFAULT NULL, date_termine DATETIME DEFAULT NULL, date_dernier_acces DATETIME DEFAULT NULL, employe_id INT NOT NULL, module_id INT NOT NULL, campagne_id INT DEFAULT NULL, INDEX IDX_5F9411351B65292 (employe_id), INDEX IDX_5F941135AFC2B591 (module_id), INDEX IDX_5F94113516227374 (campagne_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE question_quiz (id INT AUTO_INCREMENT NOT NULL, question LONGTEXT NOT NULL, type_question VARCHAR(20) NOT NULL, options JSON NOT NULL, reponses_correctes JSON NOT NULL, explication LONGTEXT DEFAULT NULL, points INT DEFAULT 10 NOT NULL, ordre INT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_FAFC177D853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quiz (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, score_minimum INT DEFAULT 70 NOT NULL, temps_limite INT DEFAULT NULL, nombre_tentatives_max INT DEFAULT 3 NOT NULL, melanger_questions TINYINT DEFAULT 1 NOT NULL, date_creation DATETIME NOT NULL, module_id INT NOT NULL, UNIQUE INDEX UNIQ_A412FA92AFC2B591 (module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resultat_phishing (id INT AUTO_INCREMENT NOT NULL, jeton_tracking_unique VARCHAR(100) NOT NULL, email_envoye TINYINT DEFAULT 0 NOT NULL, email_ouvert TINYINT DEFAULT 0 NOT NULL, lien_clique TINYINT DEFAULT 0 NOT NULL, donnees_submises TINYINT DEFAULT 0 NOT NULL, email_signale TINYINT DEFAULT 0 NOT NULL, date_envoi DATETIME DEFAULT NULL, date_ouverture DATETIME DEFAULT NULL, date_clic DATETIME DEFAULT NULL, date_submission DATETIME DEFAULT NULL, date_signalement DATETIME DEFAULT NULL, adresse_ip VARCHAR(45) DEFAULT NULL, agent_utilisateur LONGTEXT DEFAULT NULL, score INT DEFAULT 0 NOT NULL, points_gagnes INT DEFAULT 0 NOT NULL, campagne_id INT NOT NULL, employe_id INT NOT NULL, UNIQUE INDEX UNIQ_273863F8DC08935D (jeton_tracking_unique), INDEX IDX_273863F816227374 (campagne_id), INDEX IDX_273863F81B65292 (employe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resultat_simulation (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, nombre_reponses_correctes INT NOT NULL, nombre_total_questions INT NOT NULL, reponses JSON NOT NULL, a_reussi TINYINT DEFAULT 0 NOT NULL, points_gagnes INT DEFAULT 0 NOT NULL, temps_passe_secondes INT NOT NULL, date_debut DATETIME NOT NULL, date_termine DATETIME NOT NULL, employe_id INT NOT NULL, simulation_id INT NOT NULL, INDEX IDX_846552011B65292 (employe_id), INDEX IDX_84655201FEC09103 (simulation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE simulation_interactive (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type_simulation VARCHAR(50) NOT NULL, difficulte VARCHAR(20) NOT NULL, duree_estimee INT NOT NULL, contenu_simulation JSON NOT NULL, points_reussite INT DEFAULT 100 NOT NULL, seuil_reussite INT DEFAULT 70 NOT NULL, est_publie TINYINT DEFAULT 0 NOT NULL, date_creation DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tentative_quiz (id INT AUTO_INCREMENT NOT NULL, numero_tentative INT NOT NULL, score INT NOT NULL, total_questions INT NOT NULL, reponses_correctes INT NOT NULL, reponses JSON NOT NULL, a_reussi TINYINT DEFAULT 0 NOT NULL, temps_passe_secondes INT NOT NULL, date_debut DATETIME NOT NULL, date_termine DATETIME NOT NULL, employe_id INT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_A66F2D81B65292 (employe_id), INDEX IDX_A66F2D8853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, est_actif TINYINT DEFAULT 0 NOT NULL, est_verifie TINYINT DEFAULT 0 NOT NULL, est_premiere_connexion TINYINT DEFAULT 1 NOT NULL, reset_password_token VARCHAR(100) DEFAULT NULL, reset_password_expiration DATETIME DEFAULT NULL, date_creation DATETIME NOT NULL, date_derniere_connexion DATETIME DEFAULT NULL, roles JSON NOT NULL, type VARCHAR(255) NOT NULL, jeton_activation VARCHAR(100) DEFAULT NULL, jeton_expiration DATETIME DEFAULT NULL, poste VARCHAR(100) DEFAULT NULL, total_points INT DEFAULT 0, total_etoiles INT DEFAULT 0, score_vigilance DOUBLE PRECISION DEFAULT 50, departement_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), INDEX IDX_1D1C63B3CCF9E01E (departement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE campagne_formation ADD CONSTRAINT FK_C4A68CA1A715FA7 FOREIGN KEY (rssi_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE campagne_formation_module ADD CONSTRAINT FK_7BF1DEB516782339 FOREIGN KEY (campagne_formation_id) REFERENCES campagne_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE campagne_formation_module ADD CONSTRAINT FK_7BF1DEB53A53B0DC FOREIGN KEY (module_formation_id) REFERENCES module_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE campagne_phishing ADD CONSTRAINT FK_2972D574A715FA7 FOREIGN KEY (rssi_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE campagne_phishing ADD CONSTRAINT FK_2972D5748B339010 FOREIGN KEY (gabarit_id) REFERENCES gabarit_phishing (id)');
        $this->addSql('ALTER TABLE chapitre ADD CONSTRAINT FK_8C62B025AFC2B591 FOREIGN KEY (module_id) REFERENCES module_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE departement ADD CONSTRAINT FK_C1765B63A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE donnees_submises_phishing ADD CONSTRAINT FK_E8A24A4BB446785 FOREIGN KEY (resultat_phishing_id) REFERENCES resultat_phishing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gabarit_phishing ADD CONSTRAINT FK_C80D3E6E7EE5403C FOREIGN KEY (administrateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_formation ADD CONSTRAINT FK_1A213E77BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE progression_module ADD CONSTRAINT FK_5F9411351B65292 FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE progression_module ADD CONSTRAINT FK_5F941135AFC2B591 FOREIGN KEY (module_id) REFERENCES module_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE progression_module ADD CONSTRAINT FK_5F94113516227374 FOREIGN KEY (campagne_id) REFERENCES campagne_formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE question_quiz ADD CONSTRAINT FK_FAFC177D853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA92AFC2B591 FOREIGN KEY (module_id) REFERENCES module_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resultat_phishing ADD CONSTRAINT FK_273863F816227374 FOREIGN KEY (campagne_id) REFERENCES campagne_phishing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resultat_phishing ADD CONSTRAINT FK_273863F81B65292 FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resultat_simulation ADD CONSTRAINT FK_846552011B65292 FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resultat_simulation ADD CONSTRAINT FK_84655201FEC09103 FOREIGN KEY (simulation_id) REFERENCES simulation_interactive (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tentative_quiz ADD CONSTRAINT FK_A66F2D81B65292 FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tentative_quiz ADD CONSTRAINT FK_A66F2D8853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B3CCF9E01E FOREIGN KEY (departement_id) REFERENCES departement (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE campagne_formation DROP FOREIGN KEY FK_C4A68CA1A715FA7');
        $this->addSql('ALTER TABLE campagne_formation_module DROP FOREIGN KEY FK_7BF1DEB516782339');
        $this->addSql('ALTER TABLE campagne_formation_module DROP FOREIGN KEY FK_7BF1DEB53A53B0DC');
        $this->addSql('ALTER TABLE campagne_phishing DROP FOREIGN KEY FK_2972D574A715FA7');
        $this->addSql('ALTER TABLE campagne_phishing DROP FOREIGN KEY FK_2972D5748B339010');
        $this->addSql('ALTER TABLE chapitre DROP FOREIGN KEY FK_8C62B025AFC2B591');
        $this->addSql('ALTER TABLE departement DROP FOREIGN KEY FK_C1765B63A4AEAFEA');
        $this->addSql('ALTER TABLE donnees_submises_phishing DROP FOREIGN KEY FK_E8A24A4BB446785');
        $this->addSql('ALTER TABLE gabarit_phishing DROP FOREIGN KEY FK_C80D3E6E7EE5403C');
        $this->addSql('ALTER TABLE module_formation DROP FOREIGN KEY FK_1A213E77BCF5E72D');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAFB88E14F');
        $this->addSql('ALTER TABLE progression_module DROP FOREIGN KEY FK_5F9411351B65292');
        $this->addSql('ALTER TABLE progression_module DROP FOREIGN KEY FK_5F941135AFC2B591');
        $this->addSql('ALTER TABLE progression_module DROP FOREIGN KEY FK_5F94113516227374');
        $this->addSql('ALTER TABLE question_quiz DROP FOREIGN KEY FK_FAFC177D853CD175');
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY FK_A412FA92AFC2B591');
        $this->addSql('ALTER TABLE resultat_phishing DROP FOREIGN KEY FK_273863F816227374');
        $this->addSql('ALTER TABLE resultat_phishing DROP FOREIGN KEY FK_273863F81B65292');
        $this->addSql('ALTER TABLE resultat_simulation DROP FOREIGN KEY FK_846552011B65292');
        $this->addSql('ALTER TABLE resultat_simulation DROP FOREIGN KEY FK_84655201FEC09103');
        $this->addSql('ALTER TABLE tentative_quiz DROP FOREIGN KEY FK_A66F2D81B65292');
        $this->addSql('ALTER TABLE tentative_quiz DROP FOREIGN KEY FK_A66F2D8853CD175');
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B3CCF9E01E');
        $this->addSql('DROP TABLE campagne_formation');
        $this->addSql('DROP TABLE campagne_formation_module');
        $this->addSql('DROP TABLE campagne_phishing');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE chapitre');
        $this->addSql('DROP TABLE departement');
        $this->addSql('DROP TABLE donnees_submises_phishing');
        $this->addSql('DROP TABLE entreprise');
        $this->addSql('DROP TABLE gabarit_phishing');
        $this->addSql('DROP TABLE module_formation');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE progression_module');
        $this->addSql('DROP TABLE question_quiz');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('DROP TABLE resultat_phishing');
        $this->addSql('DROP TABLE resultat_simulation');
        $this->addSql('DROP TABLE simulation_interactive');
        $this->addSql('DROP TABLE tentative_quiz');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
