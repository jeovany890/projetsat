<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414182328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY `FK_1D1C63B3A4AEAFEA`');
        $this->addSql('ALTER TABLE utilisateur CHANGE role role VARCHAR(255) NOT NULL, CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT FK_1D1C63B3A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur DROP FOREIGN KEY FK_1D1C63B3A4AEAFEA');
        $this->addSql('ALTER TABLE utilisateur CHANGE role role VARCHAR(20) NOT NULL, CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
        $this->addSql('ALTER TABLE utilisateur ADD CONSTRAINT `FK_1D1C63B3A4AEAFEA` FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE SET NULL');
    }
}
