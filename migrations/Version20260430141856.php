<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430141856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs signale et soumission_donnees à resultat_phishing, supprime comportement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resultat_phishing ADD signale TINYINT(1) DEFAULT 0 NOT NULL, ADD soumission_donnees TINYINT(1) DEFAULT 0 NOT NULL, DROP comportement');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resultat_phishing ADD comportement VARCHAR(20) DEFAULT \'PASSIF\' NOT NULL, DROP signale, DROP soumission_donnees');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}