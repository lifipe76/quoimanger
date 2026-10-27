<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020122847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE action_realisations (id INT AUTO_INCREMENT NOT NULL, realise_at DATETIME NOT NULL, moment VARCHAR(20) DEFAULT NULL, complement VARCHAR(255) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, photos JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, action_id INT NOT NULL, INDEX IDX_602E934D9D32F035 (action_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE action_realisation_participants (action_realisation_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_BB631110A2BF4B4C (action_realisation_id), INDEX IDX_BB631110A76ED395 (user_id), PRIMARY KEY (action_realisation_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE actions (id INT AUTO_INCREMENT NOT NULL, designation VARCHAR(255) NOT NULL, photo VARCHAR(255) DEFAULT NULL, photos JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE action_realisations ADD CONSTRAINT FK_602E934D9D32F035 FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE action_realisation_participants ADD CONSTRAINT FK_BB631110A2BF4B4C FOREIGN KEY (action_realisation_id) REFERENCES action_realisations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE action_realisation_participants ADD CONSTRAINT FK_BB631110A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action_realisations DROP FOREIGN KEY FK_602E934D9D32F035');
        $this->addSql('ALTER TABLE action_realisation_participants DROP FOREIGN KEY FK_BB631110A2BF4B4C');
        $this->addSql('ALTER TABLE action_realisation_participants DROP FOREIGN KEY FK_BB631110A76ED395');
        $this->addSql('DROP TABLE action_realisations');
        $this->addSql('DROP TABLE action_realisation_participants');
        $this->addSql('DROP TABLE actions');
    }
}
