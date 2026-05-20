<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509085903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE progression_module ADD type_attribution VARCHAR(30) DEFAULT \'CAMPAGNE\' NOT NULL, ADD progression_parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE progression_module ADD CONSTRAINT FK_5F9411353DE2E9B8 FOREIGN KEY (progression_parent_id) REFERENCES progression_module (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5F9411353DE2E9B8 ON progression_module (progression_parent_id)');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE progression_module DROP FOREIGN KEY FK_5F9411353DE2E9B8');
        $this->addSql('DROP INDEX IDX_5F9411353DE2E9B8 ON progression_module');
        $this->addSql('ALTER TABLE progression_module DROP type_attribution, DROP progression_parent_id');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
