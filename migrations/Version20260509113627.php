<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509113627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_progression_employe_module_campagne ON progression_module');
        $this->addSql('ALTER TABLE resultat_simulation ADD progression_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE resultat_simulation ADD CONSTRAINT FK_84655201AF229C18 FOREIGN KEY (progression_id) REFERENCES progression_module (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_84655201AF229C18 ON resultat_simulation (progression_id)');
        $this->addSql('ALTER TABLE tentative_quiz ADD progression_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tentative_quiz ADD CONSTRAINT FK_A66F2D8AF229C18 FOREIGN KEY (progression_id) REFERENCES progression_module (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A66F2D8AF229C18 ON tentative_quiz (progression_id)');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_progression_employe_module_campagne ON progression_module (employe_id, module_id, campagne_id)');
        $this->addSql('ALTER TABLE resultat_simulation DROP FOREIGN KEY FK_84655201AF229C18');
        $this->addSql('DROP INDEX IDX_84655201AF229C18 ON resultat_simulation');
        $this->addSql('ALTER TABLE resultat_simulation DROP progression_id');
        $this->addSql('ALTER TABLE tentative_quiz DROP FOREIGN KEY FK_A66F2D8AF229C18');
        $this->addSql('DROP INDEX IDX_A66F2D8AF229C18 ON tentative_quiz');
        $this->addSql('ALTER TABLE tentative_quiz DROP progression_id');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
