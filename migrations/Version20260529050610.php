<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260529050610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE simulation_interactive');
        $this->addSql('ALTER TABLE resultat_phishing ADD date_planifiee DATETIME NOT NULL, ADD message_erreur LONGTEXT DEFAULT NULL, ADD nombre_tentatives INT DEFAULT 0 NOT NULL, ADD date_creation DATETIME NOT NULL, DROP email_envoye, DROP envoi_id');
        $this->addSql('ALTER TABLE resultat_simulation DROP FOREIGN KEY `FK_RESULTAT_MODULE`');
        $this->addSql('ALTER TABLE resultat_simulation ADD reponses_correctes INT NOT NULL, DROP score, DROP nombre_reponses_correctes, DROP nombre_total_questions, DROP a_reussi, DROP points_gagnes, CHANGE module_id module_id INT NOT NULL');
        $this->addSql('DROP INDEX fk_resultat_module ON resultat_simulation');
        $this->addSql('CREATE INDEX IDX_84655201AFC2B591 ON resultat_simulation (module_id)');
        $this->addSql('ALTER TABLE resultat_simulation ADD CONSTRAINT `FK_RESULTAT_MODULE` FOREIGN KEY (module_id) REFERENCES module_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tentative_quiz DROP FOREIGN KEY `FK_TENTATIVE_CHAPITRE`');
        $this->addSql('ALTER TABLE tentative_quiz DROP score, DROP total_questions, DROP a_reussi, CHANGE chapitre_id chapitre_id INT NOT NULL');
        $this->addSql('DROP INDEX fk_tentative_chapitre ON tentative_quiz');
        $this->addSql('CREATE INDEX IDX_A66F2D81FBEEF7B ON tentative_quiz (chapitre_id)');
        $this->addSql('ALTER TABLE tentative_quiz ADD CONSTRAINT `FK_TENTATIVE_CHAPITRE` FOREIGN KEY (chapitre_id) REFERENCES chapitre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE simulation_interactive (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, type_simulation VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, difficulte VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, duree_estimee INT NOT NULL, contenu_simulation JSON NOT NULL, points_reussite INT DEFAULT 100 NOT NULL, est_publie TINYINT DEFAULT 0 NOT NULL, date_creation DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE resultat_phishing ADD email_envoye TINYINT DEFAULT 0 NOT NULL, ADD envoi_id INT DEFAULT NULL, DROP date_planifiee, DROP message_erreur, DROP nombre_tentatives, DROP date_creation');
        $this->addSql('ALTER TABLE resultat_simulation DROP FOREIGN KEY FK_84655201AFC2B591');
        $this->addSql('ALTER TABLE resultat_simulation ADD nombre_reponses_correctes INT NOT NULL, ADD nombre_total_questions INT NOT NULL, ADD a_reussi TINYINT DEFAULT 0 NOT NULL, ADD points_gagnes INT DEFAULT 0 NOT NULL, CHANGE module_id module_id INT DEFAULT NULL, CHANGE reponses_correctes score INT NOT NULL');
        $this->addSql('DROP INDEX idx_84655201afc2b591 ON resultat_simulation');
        $this->addSql('CREATE INDEX FK_RESULTAT_MODULE ON resultat_simulation (module_id)');
        $this->addSql('ALTER TABLE resultat_simulation ADD CONSTRAINT FK_84655201AFC2B591 FOREIGN KEY (module_id) REFERENCES module_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tentative_quiz DROP FOREIGN KEY FK_A66F2D81FBEEF7B');
        $this->addSql('ALTER TABLE tentative_quiz ADD score INT NOT NULL, ADD total_questions INT NOT NULL, ADD a_reussi TINYINT DEFAULT 0 NOT NULL, CHANGE chapitre_id chapitre_id INT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_a66f2d81fbeef7b ON tentative_quiz');
        $this->addSql('CREATE INDEX FK_TENTATIVE_CHAPITRE ON tentative_quiz (chapitre_id)');
        $this->addSql('ALTER TABLE tentative_quiz ADD CONSTRAINT FK_A66F2D81FBEEF7B FOREIGN KEY (chapitre_id) REFERENCES chapitre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
