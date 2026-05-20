<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509161402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entreprise DROP nombre_employes');
        $this->addSql('ALTER TABLE progression_module DROP temps_passe_minutes, DROP score_quiz, DROP points_gagnes, DROP etoiles_gagnees, DROP est_en_retard');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE entreprise ADD nombre_employes INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE progression_module ADD temps_passe_minutes INT DEFAULT 0 NOT NULL, ADD score_quiz INT DEFAULT NULL, ADD points_gagnes INT DEFAULT 0 NOT NULL, ADD etoiles_gagnees INT DEFAULT 0 NOT NULL, ADD est_en_retard TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
