<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504042740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE module_formation DROP FOREIGN KEY `FK_module_simulation`');
        $this->addSql('ALTER TABLE module_formation ADD CONSTRAINT FK_1A213E77FEC09103 FOREIGN KEY (simulation_id) REFERENCES simulation_interactive (id)');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE module_formation DROP FOREIGN KEY FK_1A213E77FEC09103');
        $this->addSql('ALTER TABLE module_formation ADD CONSTRAINT `FK_module_simulation` FOREIGN KEY (simulation_id) REFERENCES simulation_interactive (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
