<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003222431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE realisation_notes (id INT AUTO_INCREMENT NOT NULL, note SMALLINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, realisation_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_REALISATION_USER (realisation_id, user_id), INDEX IDX_CBBBC9DCB685E551 (realisation_id), INDEX IDX_CBBBC9DCA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE realisation_notes ADD CONSTRAINT FK_CBBBC9DCB685E551 FOREIGN KEY (realisation_id) REFERENCES recette_realisations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE realisation_notes ADD CONSTRAINT FK_CBBBC9DCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE realisation_notes DROP FOREIGN KEY FK_CBBBC9DCB685E551');
        $this->addSql('ALTER TABLE realisation_notes DROP FOREIGN KEY FK_CBBBC9DCA76ED395');
        $this->addSql('DROP TABLE realisation_notes');
    }
}
