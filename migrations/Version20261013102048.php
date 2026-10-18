<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013102048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE frigos (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, recette_id INT NOT NULL, user_id INT NOT NULL, famille_id INT DEFAULT NULL, INDEX IDX_CCA2CC8989312FE9 (recette_id), INDEX IDX_CCA2CC89A76ED395 (user_id), INDEX IDX_CCA2CC8997A77B84 (famille_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT FK_CCA2CC8989312FE9 FOREIGN KEY (recette_id) REFERENCES recettes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT FK_CCA2CC89A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT FK_CCA2CC8997A77B84 FOREIGN KEY (famille_id) REFERENCES familles (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY FK_CCA2CC8989312FE9');
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY FK_CCA2CC89A76ED395');
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY FK_CCA2CC8997A77B84');
        $this->addSql('DROP TABLE frigos');
    }
}
