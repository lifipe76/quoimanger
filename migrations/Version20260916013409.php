<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916013409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ingredients (id INT AUTO_INCREMENT NOT NULL, designation VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recette_ingredients (id INT AUTO_INCREMENT NOT NULL, quantite VARCHAR(255) DEFAULT NULL, recette_id INT NOT NULL, ingredient_id INT NOT NULL, INDEX IDX_B413140689312FE9 (recette_id), INDEX IDX_B4131406933FE08C (ingredient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recettes (id INT AUTO_INCREMENT NOT NULL, designation VARCHAR(255) NOT NULL, realise_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, app_token VARCHAR(255) DEFAULT NULL, email VARCHAR(255) NOT NULL, firstname VARCHAR(255) DEFAULT NULL, lastname VARCHAR(255) DEFAULT NULL, civility INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE recette_ingredients ADD CONSTRAINT FK_B413140689312FE9 FOREIGN KEY (recette_id) REFERENCES recettes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recette_ingredients ADD CONSTRAINT FK_B4131406933FE08C FOREIGN KEY (ingredient_id) REFERENCES ingredients (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recette_ingredients DROP FOREIGN KEY FK_B413140689312FE9');
        $this->addSql('ALTER TABLE recette_ingredients DROP FOREIGN KEY FK_B4131406933FE08C');
        $this->addSql('DROP TABLE ingredients');
        $this->addSql('DROP TABLE recette_ingredients');
        $this->addSql('DROP TABLE recettes');
        $this->addSql('DROP TABLE users');
    }
}
