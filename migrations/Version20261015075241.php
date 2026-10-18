<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261015075241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE boissons (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, lieu_achat VARCHAR(255) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, photos JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE frigos (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, recette_id INT NOT NULL, user_id INT NOT NULL, famille_id INT DEFAULT NULL, INDEX IDX_CCA2CC8989312FE9 (recette_id), INDEX IDX_CCA2CC89A76ED395 (user_id), INDEX IDX_CCA2CC8997A77B84 (famille_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE repas_boissons (id INT AUTO_INCREMENT NOT NULL, date_achat DATE DEFAULT NULL, note SMALLINT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, realisation_id INT NOT NULL, boisson_id INT NOT NULL, INDEX IDX_BA29A5D5B685E551 (realisation_id), INDEX IDX_BA29A5D5734B8089 (boisson_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT FK_CCA2CC8989312FE9 FOREIGN KEY (recette_id) REFERENCES recettes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT FK_CCA2CC89A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT FK_CCA2CC8997A77B84 FOREIGN KEY (famille_id) REFERENCES familles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas_boissons ADD CONSTRAINT FK_BA29A5D5B685E551 FOREIGN KEY (realisation_id) REFERENCES recette_realisations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas_boissons ADD CONSTRAINT FK_BA29A5D5734B8089 FOREIGN KEY (boisson_id) REFERENCES boissons (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY FK_CCA2CC8989312FE9');
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY FK_CCA2CC89A76ED395');
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY FK_CCA2CC8997A77B84');
        $this->addSql('ALTER TABLE repas_boissons DROP FOREIGN KEY FK_BA29A5D5B685E551');
        $this->addSql('ALTER TABLE repas_boissons DROP FOREIGN KEY FK_BA29A5D5734B8089');
        $this->addSql('DROP TABLE boissons');
        $this->addSql('DROP TABLE frigos');
        $this->addSql('DROP TABLE repas_boissons');
    }
}
