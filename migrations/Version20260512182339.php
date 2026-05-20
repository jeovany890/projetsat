<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512182339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE module_formation ADD points_bonus INT DEFAULT 20 NOT NULL, DROP points_reussite, DROP etoiles_reussite');
        $this->addSql('ALTER TABLE quiz ADD points INT DEFAULT 5 NOT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE module_formation ADD points_reussite INT DEFAULT 100 NOT NULL, ADD etoiles_reussite INT DEFAULT 2 NOT NULL, DROP points_bonus');
        $this->addSql('ALTER TABLE quiz DROP points');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
