<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428130245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 2 — Inversion FK EnvoiPhishing/ResultatPhishing, traçabilité admin, UNIQUE progression';
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :table
               AND COLUMN_NAME  = :column",
            ['table' => $table, 'column' => $column]
        );
        return (int) $result > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :table
               AND INDEX_NAME   = :index",
            ['table' => $table, 'index' => $index]
        );
        return (int) $result > 0;
    }

    private function fkExists(string $table, string $fk): bool
    {
        $result = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA    = DATABASE()
               AND TABLE_NAME      = :table
               AND CONSTRAINT_NAME = :fk
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            ['table' => $table, 'fk' => $fk]
        );
        return (int) $result > 0;
    }

    public function up(Schema $schema): void
    {
        // 1. RESULTAT_PHISHING — ADD comportement et envoi_id si absents
        $adds = [];
        if (!$this->columnExists('resultat_phishing', 'comportement')) {
            $adds[] = "ADD comportement VARCHAR(20) NOT NULL DEFAULT 'PASSIF'";
        }
        if (!$this->columnExists('resultat_phishing', 'envoi_id')) {
            $adds[] = 'ADD envoi_id INT DEFAULT NULL';
        }
        if (!empty($adds)) {
            $this->addSql('ALTER TABLE resultat_phishing ' . implode(', ', $adds));
        }

        // Migration de données UNIQUEMENT si resultat_id existe encore dans envoi_phishing
        // (peut déjà avoir été supprimée par une migration précédente)
        if ($this->columnExists('envoi_phishing', 'resultat_id')) {
            $this->addSql('
                UPDATE resultat_phishing rp
                INNER JOIN envoi_phishing ep ON ep.resultat_id = rp.id
                SET rp.envoi_id = ep.id
            ');
        }

        // FK et index UNIQUE sur envoi_id
        if (!$this->fkExists('resultat_phishing', 'FK_RP_ENVOI')) {
            $this->addSql('ALTER TABLE resultat_phishing
                ADD CONSTRAINT FK_RP_ENVOI FOREIGN KEY (envoi_id)
                    REFERENCES envoi_phishing (id) ON DELETE SET NULL
            ');
        }
        if (!$this->indexExists('resultat_phishing', 'UNIQ_RP_ENVOI')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_RP_ENVOI ON resultat_phishing (envoi_id)');
        }

        // 2. ENVOI_PHISHING — DROP resultat_id si elle existe encore
        if ($this->fkExists('envoi_phishing', 'FK_B12E6394D233E95C')) {
            $this->addSql('ALTER TABLE envoi_phishing DROP FOREIGN KEY FK_B12E6394D233E95C');
        }
        if ($this->indexExists('envoi_phishing', 'UNIQ_B12E6394D233E95C')) {
            $this->addSql('DROP INDEX UNIQ_B12E6394D233E95C ON envoi_phishing');
        }
        if ($this->columnExists('envoi_phishing', 'resultat_id')) {
            $this->addSql('ALTER TABLE envoi_phishing DROP COLUMN resultat_id');
        }

        // 3. ENTREPRISE — ADD valide_by si absent
        if (!$this->columnExists('entreprise', 'valide_by')) {
            $this->addSql('ALTER TABLE entreprise ADD valide_by INT DEFAULT NULL');
            if (!$this->fkExists('entreprise', 'FK_ENT_VALIDE_BY')) {
                $this->addSql('ALTER TABLE entreprise
                    ADD CONSTRAINT FK_ENT_VALIDE_BY FOREIGN KEY (valide_by)
                        REFERENCES utilisateur (id) ON DELETE SET NULL
                ');
            }
            if (!$this->indexExists('entreprise', 'IDX_ENT_VALIDE_BY')) {
                $this->addSql('CREATE INDEX IDX_ENT_VALIDE_BY ON entreprise (valide_by)');
            }
        }

        // 4. PROGRESSION_MODULE — ADD UNIQUE si absent
        if (!$this->indexExists('progression_module', 'UQ_PROGRESSION_EMPLOYE_MODULE')) {
            $this->addSql('ALTER TABLE progression_module
                ADD CONSTRAINT UQ_PROGRESSION_EMPLOYE_MODULE UNIQUE (employe_id, module_id)
            ');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE progression_module DROP INDEX UQ_PROGRESSION_EMPLOYE_MODULE');

        $this->addSql('ALTER TABLE entreprise DROP FOREIGN KEY FK_ENT_VALIDE_BY');
        $this->addSql('DROP INDEX IDX_ENT_VALIDE_BY ON entreprise');
        $this->addSql('ALTER TABLE entreprise DROP COLUMN valide_by');

        $this->addSql('ALTER TABLE envoi_phishing ADD resultat_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE envoi_phishing
            ADD CONSTRAINT FK_B12E6394D233E95C FOREIGN KEY (resultat_id)
                REFERENCES resultat_phishing (id) ON DELETE SET NULL
        ');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B12E6394D233E95C ON envoi_phishing (resultat_id)');

        $this->addSql('ALTER TABLE resultat_phishing DROP FOREIGN KEY FK_RP_ENVOI');
        $this->addSql('DROP INDEX UNIQ_RP_ENVOI ON resultat_phishing');
        $this->addSql('ALTER TABLE resultat_phishing
            DROP COLUMN envoi_id,
            DROP COLUMN comportement
        ');
    }
}