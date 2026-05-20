<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428132052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entreprise DROP FOREIGN KEY FK_D19FA6063A4095B');
        $this->addSql('DROP INDEX IDX_D19FA6063A4095B ON entreprise');
        $this->addSql('ALTER TABLE entreprise DROP valide_by');
        $this->addSql('DROP INDEX UNIQ_progression_employe_module ON progression_module');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
