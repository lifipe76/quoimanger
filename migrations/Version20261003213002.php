<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003213002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE famille_invitations (id INT AUTO_INCREMENT NOT NULL, invite_email VARCHAR(255) NOT NULL, statut VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, famille_id INT NOT NULL, demandeur_id INT NOT NULL, invite_user_id INT DEFAULT NULL, INDEX IDX_15D0AEC597A77B84 (famille_id), INDEX IDX_15D0AEC595A6EE59 (demandeur_id), INDEX IDX_15D0AEC5B8995F61 (invite_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE familles (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE realisation_participants (recette_realisation_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_C2C9B55E7DB58722 (recette_realisation_id), INDEX IDX_C2C9B55EA76ED395 (user_id), PRIMARY KEY (recette_realisation_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE famille_invitations ADD CONSTRAINT FK_15D0AEC597A77B84 FOREIGN KEY (famille_id) REFERENCES familles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE famille_invitations ADD CONSTRAINT FK_15D0AEC595A6EE59 FOREIGN KEY (demandeur_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE famille_invitations ADD CONSTRAINT FK_15D0AEC5B8995F61 FOREIGN KEY (invite_user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE realisation_participants ADD CONSTRAINT FK_C2C9B55E7DB58722 FOREIGN KEY (recette_realisation_id) REFERENCES recette_realisations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE realisation_participants ADD CONSTRAINT FK_C2C9B55EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users ADD famille_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E997A77B84 FOREIGN KEY (famille_id) REFERENCES familles (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1483A5E997A77B84 ON users (famille_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE famille_invitations DROP FOREIGN KEY FK_15D0AEC597A77B84');
        $this->addSql('ALTER TABLE famille_invitations DROP FOREIGN KEY FK_15D0AEC595A6EE59');
        $this->addSql('ALTER TABLE famille_invitations DROP FOREIGN KEY FK_15D0AEC5B8995F61');
        $this->addSql('ALTER TABLE realisation_participants DROP FOREIGN KEY FK_C2C9B55E7DB58722');
        $this->addSql('ALTER TABLE realisation_participants DROP FOREIGN KEY FK_C2C9B55EA76ED395');
        $this->addSql('DROP TABLE famille_invitations');
        $this->addSql('DROP TABLE familles');
        $this->addSql('DROP TABLE realisation_participants');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E997A77B84');
        $this->addSql('DROP INDEX IDX_1483A5E997A77B84 ON users');
        $this->addSql('ALTER TABLE users DROP famille_id');
    }
}
