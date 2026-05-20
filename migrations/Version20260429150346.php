<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260429150346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convertit les questions de question_quiz en JSON dans quiz, et les catégories en champ texte dans module_formation.';
    }

    public function up(Schema $schema): void
    {
        // ======================================================
        // 1. Ajouter la colonne JSON 'questions' dans quiz
        // ======================================================
        $this->addSql('ALTER TABLE quiz ADD questions JSON DEFAULT NULL');

        // 2. Convertir les anciennes questions depuis question_quiz
        // 2.1 Récupérer tous les quiz
        $quizRows = $this->connection->fetchAllAssociative('SELECT id FROM quiz');
        foreach ($quizRows as $quizRow) {
            $quizId = $quizRow['id'];
            // Récupérer les questions de ce quiz
            $questionsData = $this->connection->fetchAllAssociative(
                'SELECT question, type_question, options, reponses_correctes, points, explication 
                 FROM question_quiz 
                 WHERE quiz_id = :quiz_id 
                 ORDER BY id ASC',
                ['quiz_id' => $quizId]
            );
            if (empty($questionsData)) {
                continue;
            }
            $formatted = [];
            foreach ($questionsData as $q) {
                $formatted[] = [
                    'question'           => $q['question'],
                    'type_question'      => $q['type_question'],
                    'options'            => json_decode($q['options'], true) ?: [],
                    'reponses_correctes' => json_decode($q['reponses_correctes'], true) ?: [],
                    'points'             => (int) $q['points'],
                    'explication'        => $q['explication'],
                ];
            }
            $json = json_encode($formatted, JSON_THROW_ON_ERROR);
            $this->addSql('UPDATE quiz SET questions = :json WHERE id = :id', ['json' => $json, 'id' => $quizId]);
        }

        // 3. Supprimer la table question_quiz (après conversion)
        $this->addSql('DROP TABLE question_quiz');

        // ======================================================
        // 4. Ajouter la colonne texte 'categorie' dans module_formation
        // ======================================================
        $this->addSql('ALTER TABLE module_formation ADD categorie VARCHAR(100) DEFAULT NULL');

        // 5. Remplir la colonne 'categorie' avec le titre de la catégorie liée
        $this->addSql('UPDATE module_formation m INNER JOIN categorie c ON m.categorie_id = c.id SET m.categorie = c.titre');

        // 6. Supprimer la clé étrangère et la colonne categorie_id
        $this->addSql('ALTER TABLE module_formation DROP FOREIGN KEY FK_1A213E77BCF5E72D');
        $this->addSql('ALTER TABLE module_formation DROP categorie_id');

        // 7. Supprimer la table categorie
        $this->addSql('DROP TABLE categorie');

        // ======================================================
        // Conserver toutes les autres modifications générées par Symfony
        // (celles qui concernent entreprise, progression_module, etc.)
        // ======================================================
        $this->addSql('ALTER TABLE entreprise DROP FOREIGN KEY `FK_ENT_VALIDE_BY`');
        $this->addSql('DROP INDEX idx_ent_valide_by ON entreprise');
        $this->addSql('CREATE INDEX IDX_D19FA6063A4095B ON entreprise (valide_by)');
        $this->addSql('ALTER TABLE entreprise ADD CONSTRAINT `FK_ENT_VALIDE_BY` FOREIGN KEY (valide_by) REFERENCES utilisateur (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE progression_module DROP FOREIGN KEY `FK_5F9411351B65292`');
        $this->addSql('ALTER TABLE progression_module DROP FOREIGN KEY `FK_5F941135AFC2B591`');
        $this->addSql('DROP INDEX uq_progression_employe_module ON progression_module');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_progression_employe_module ON progression_module (employe_id, module_id)');
        $this->addSql('ALTER TABLE progression_module ADD CONSTRAINT `FK_5F9411351B65292` FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE progression_module ADD CONSTRAINT `FK_5F941135AFC2B591` FOREIGN KEY (module_id) REFERENCES module_formation (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE resultat_phishing DROP FOREIGN KEY `FK_RP_ENVOI`');
        $this->addSql('DROP INDEX UNIQ_RP_ENVOI ON resultat_phishing');

        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // La migration down déjà générée par Symfony reste valide (elle recréé les tables)
        // On la conserve telle quelle
        $this->addSql('CREATE TABLE categorie (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, statut VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'ACTIF\' NOT NULL COLLATE `utf8mb4_general_ci`, date_creation DATETIME NOT NULL, UNIQUE INDEX UNIQ_497DD634FF7747B4 (titre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE question_quiz (id INT AUTO_INCREMENT NOT NULL, question LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type_question VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, options JSON NOT NULL, reponses_correctes JSON NOT NULL, explication LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, points INT DEFAULT 10 NOT NULL, quiz_id INT NOT NULL, INDEX IDX_FAFC177D853CD175 (quiz_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE question_quiz ADD CONSTRAINT `FK_FAFC177D853CD175` FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entreprise DROP FOREIGN KEY FK_D19FA6063A4095B');
        $this->addSql('DROP INDEX idx_d19fa6063a4095b ON entreprise');
        $this->addSql('CREATE INDEX IDX_ENT_VALIDE_BY ON entreprise (valide_by)');
        $this->addSql('ALTER TABLE entreprise ADD CONSTRAINT FK_D19FA6063A4095B FOREIGN KEY (valide_by) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE module_formation ADD categorie_id INT NOT NULL, DROP categorie');
        $this->addSql('ALTER TABLE module_formation ADD CONSTRAINT `FK_1A213E77BCF5E72D` FOREIGN KEY (categorie_id) REFERENCES categorie (id)');
        $this->addSql('CREATE INDEX IDX_1A213E77BCF5E72D ON module_formation (categorie_id)');
        $this->addSql('ALTER TABLE progression_module DROP FOREIGN KEY FK_5F9411351B65292');
        $this->addSql('ALTER TABLE progression_module DROP FOREIGN KEY FK_5F941135AFC2B591');
        $this->addSql('DROP INDEX uniq_progression_employe_module ON progression_module');
        $this->addSql('CREATE UNIQUE INDEX UQ_PROGRESSION_EMPLOYE_MODULE ON progression_module (employe_id, module_id)');
        $this->addSql('ALTER TABLE progression_module ADD CONSTRAINT FK_5F9411351B65292 FOREIGN KEY (employe_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE progression_module ADD CONSTRAINT FK_5F941135AFC2B591 FOREIGN KEY (module_id) REFERENCES module_formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz DROP questions');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_RP_ENVOI ON resultat_phishing (envoi_id)');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}