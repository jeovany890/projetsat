<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260503200856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur DROP total_etoiles, DROP taux_phishing, DROP taux_smishing, DROP taux_bonnes_pratiques, CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE utilisateur ADD total_etoiles INT DEFAULT 0, ADD taux_phishing DOUBLE PRECISION DEFAULT \'0\', ADD taux_smishing DOUBLE PRECISION DEFAULT \'0\', ADD taux_bonnes_pratiques DOUBLE PRECISION DEFAULT \'0\', CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
