<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316095220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quiz ADD chapitre_id INT DEFAULT NULL, CHANGE module_id module_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA921FBEEF7B FOREIGN KEY (chapitre_id) REFERENCES chapitre (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A412FA921FBEEF7B ON quiz (chapitre_id)');
        $this->addSql('ALTER TABLE simulation_interactive ADD points_echec INT DEFAULT 0 NOT NULL, ADD module_id INT DEFAULT NULL, DROP seuil_reussite');
        $this->addSql('ALTER TABLE simulation_interactive ADD CONSTRAINT FK_7C664B8AAFC2B591 FOREIGN KEY (module_id) REFERENCES module_formation (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7C664B8AAFC2B591 ON simulation_interactive (module_id)');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT 50');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY FK_A412FA921FBEEF7B');
        $this->addSql('DROP INDEX UNIQ_A412FA921FBEEF7B ON quiz');
        $this->addSql('ALTER TABLE quiz DROP chapitre_id, CHANGE module_id module_id INT NOT NULL');
        $this->addSql('ALTER TABLE simulation_interactive DROP FOREIGN KEY FK_7C664B8AAFC2B591');
        $this->addSql('DROP INDEX UNIQ_7C664B8AAFC2B591 ON simulation_interactive');
        $this->addSql('ALTER TABLE simulation_interactive ADD seuil_reussite INT DEFAULT 70 NOT NULL, DROP points_echec, DROP module_id');
        $this->addSql('ALTER TABLE utilisateur CHANGE score_vigilance score_vigilance DOUBLE PRECISION DEFAULT \'50\'');
    }
}
